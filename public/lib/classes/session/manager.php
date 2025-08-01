<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace core\session;

use core\clock;
use core\di;
use html_writer;

/**
 * Session manager, this is the public Moodle API for sessions.
 *
 * Following PHP functions MUST NOT be used directly:
 * - session_start() - not necessary, lib/setup.php starts session automatically,
 *   use define('NO_MOODLE_COOKIE', true) if session not necessary.
 * - session_write_close() - use \core\session\manager::write_close() instead.
 * - session_destroy() - use require_logout() instead.
 *
 * @package    core
 * @copyright  2013 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /** @var int A hard cutoff of maximum stored history */
    const MAXIMUM_STORED_SESSION_HISTORY = 50;

    /** @var int The recent session locks array is reset if there is a time gap more than this value in seconds */
    const SESSION_RESET_GAP_THRESHOLD = 1;

    /** @var handler $handler active session handler instance */
    protected static $handler;

    /** @var bool $sessionactive Is the session active? */
    protected static $sessionactive = null;

    /** @var string $logintokenkey Key used to get and store request protection for login form. */
    protected static $logintokenkey = 'core_auth_login';

    /** @var array Stores the the SESSION before a request is performed, used to check incorrect read-only modes */
    private static $priorsession = [];

    /** @var array Stores the the SESSION after write_close is called, used to check if it was mutated after the session is closed */
    private static $sessionatclose = [];

    /**
     * @var bool Used to trigger the SESSION mutation warning without actually preventing SESSION mutation.
     * This variable is used to "copy" what the $requireslock parameter  does in start_session().
     * Once requireslock is set in start_session it's later accessible via $handler->requires_write_lock,
     * When using $CFG->enable_read_only_sessions_debug mode, this variable serves the same purpose without
     * actually setting the handler as requiring a write lock.
     */
    private static $requireslockdebug;

    /**
     * If the current session is not writeable, abort it, and re-open it
     * requesting (and blocking) until a write lock is acquired.
     * If current session was already opened with an intentional write lock,
     * this call will not do anything.
     * NOTE: Even when using a session handler that does not support non-locking sessions,
     * if the original session was not opened with the explicit intention of being locked,
     * this will still restart your session so that code behaviour matches as closely
     * as practical across environments.
     *
     * @param bool $readonlysession Used by debugging logic to determine if whatever
     *                              triggered the restart (e.g., a webservice) declared
     *                              itself as read only.
     */
    public static function restart_with_write_lock(bool $readonlysession) {
        global $CFG;

        if (!empty($CFG->enable_read_only_sessions) || !empty($CFG->enable_read_only_sessions_debug)) {
            self::$requireslockdebug = !$readonlysession;
        }

        if (self::$sessionactive && !self::$handler->requires_write_lock()) {
            @self::$handler->abort();
            self::$sessionactive = false;
            self::start_session(true);
        }
    }

    /**
     * Start user session.
     *
     * Note: This is intended to be called only from lib/setup.php!
     */
    public static function start() {
        global $CFG, $DB, $PERF;

        if (isset(self::$sessionactive)) {
            debugging('Session was already started!', DEBUG_DEVELOPER);
            return;
        }

        // Grab the time before session lock starts.
        $PERF->sessionlock['start'] = microtime(true);
        self::load_handler();

        // Init the session handler only if everything initialised properly in lib/setup.php file
        // and the session is actually required.
        if (empty($DB) or empty($CFG->version) or !defined('NO_MOODLE_COOKIES') or NO_MOODLE_COOKIES or CLI_SCRIPT) {
            self::$sessionactive = false;
            self::init_empty_session();
            return;
        }

        if (defined('READ_ONLY_SESSION') && !empty($CFG->enable_read_only_sessions)) {
            $requireslock = !READ_ONLY_SESSION;
        } else {
            $requireslock = true; // For backwards compatibility, we default to assuming that a lock is needed.
        }

        // By default make the two variables the same. This means that when they are
        // checked below in start_session and write_close there is no possibility for
        // the debug version to "accidentally" execute the debug mode.
        self::$requireslockdebug = $requireslock;
        if (defined('READ_ONLY_SESSION') && !empty($CFG->enable_read_only_sessions_debug)) {
            // Only change the debug variable if READ_ONLY_SESSION is declared,
            // as would happen with the real requireslock variable.
            self::$requireslockdebug = !READ_ONLY_SESSION;
        }

        self::start_session($requireslock);
    }

    /**
     * Handles starting a session.
     *
     * @param bool $requireslock If this is false then no write lock will be acquired,
     *                           and the session will be read-only.
     */
    private static function start_session(bool $requireslock) {
        global $PERF, $CFG;

        try {
            self::$handler->init();
            self::$handler->set_requires_write_lock($requireslock);
            self::prepare_cookies();
            $isnewsession = empty($_COOKIE[session_name()]);

            if (!self::$handler->start()) {
                // Could not successfully start/recover session.
                throw new \core\session\exception('sessionstarterror', 'error');
            }

            if ($requireslock) {
                // Grab the time when session lock starts.
                $PERF->sessionlock['gained'] = microtime(true);
                $PERF->sessionlock['wait'] = $PERF->sessionlock['gained'] - $PERF->sessionlock['start'];
            }
            self::initialise_user_session($isnewsession);
            self::$sessionactive = true; // Set here, so the session can be cleared if the security check fails.
            self::check_security();

            if (!$requireslock || !self::$requireslockdebug) {
                self::$priorsession = (array) $_SESSION['SESSION'];
            }

            if (!empty($CFG->enable_read_only_sessions) && isset($_SESSION['SESSION']->cachestore_session)) {
                $caches = join(', ', array_keys($_SESSION['SESSION']->cachestore_session));
                $caches = str_replace('default_session-', '', $caches);
                throw new \moodle_exception("The session caches can not be in the session store when "
                    . "enable_read_only_sessions is enabled. Please map all session mode caches to be outside of the "
                    . "default session store before enabling this features. Found these definitions in the session: $caches");
            }

            // Link global $USER and $SESSION,
            // this is tricky because PHP does not allow references to references
            // and global keyword uses internally once reference to the $GLOBALS array.
            // The solution is to use the $GLOBALS['USER'] and $GLOBALS['$SESSION']
            // as the main storage of data and put references to $_SESSION.
            $GLOBALS['USER'] = $_SESSION['USER'];
            $_SESSION['USER'] =& $GLOBALS['USER'];
            $GLOBALS['SESSION'] = $_SESSION['SESSION'];
            $_SESSION['SESSION'] =& $GLOBALS['SESSION'];

        } catch (\Exception $ex) {
            self::init_empty_session();
            self::$sessionactive = false;
            throw $ex;
        }
    }

    /**
     * Returns current page performance info.
     *
     * @return array perf info
     */
    public static function get_performance_info() {
        global $CFG, $PERF;

        if (!session_id()) {
            return array();
        }

        self::load_handler();
        $size = display_size(strlen(session_encode()));
        $handler = get_class(self::$handler);

        $info = array();
        $info['size'] = $size;
        $info['html'] = html_writer::div("Session ($handler): $size", "sessionsize");
        $info['txt'] = "Session ($handler): $size ";

        if (!empty($CFG->debugsessionlock)) {
            $sessionlock = self::get_session_lock_info();
            if (!empty($sessionlock['held'])) {
                // The page displays the footer and the session has been closed.
                $sessionlocktext = "Session lock held: ".number_format($sessionlock['held'], 3)." secs";
            } else {
                // The session hasn't yet been closed and so we assume now with microtime.
                $sessionlockheld = microtime(true) - $PERF->sessionlock['gained'];
                $sessionlocktext = "Session lock open: ".number_format($sessionlockheld, 3)." secs";
            }
            $info['txt'] .= $sessionlocktext;
            $info['html'] .= html_writer::div($sessionlocktext, "sessionlockstart");
            $sessionlockwaittext = "Session lock wait: ".number_format($sessionlock['wait'], 3)." secs";
            $info['txt'] .= $sessionlockwaittext;
            $info['html'] .= html_writer::div($sessionlockwaittext, "sessionlockwait");
        }

        return $info;
    }

    /**
     * Get fully qualified name of session handler class.
     *
     * @return string The name of the handler class
     */
    public static function get_handler_class() {
        global $CFG, $DB;

        if (PHPUNIT_TEST) {
            return \core\tests\session\mock_handler::class;
        } else if (!empty($CFG->session_handler_class)) {
            return $CFG->session_handler_class;
        } else if (!empty($CFG->dbsessions) && $DB->session_lock_supported()) {
            return database::class;
        }

        return file::class;
    }

    /**
     * Create handler instance.
     */
    protected static function load_handler() {
        if (self::$handler) {
            return;
        }

        // Find out which handler to use.
        $class = self::get_handler_class();
        self::$handler = new $class();
        if (!self::$handler instanceof \core\session\handler) {
            throw new exception("$class must implement the \core\session\handler");
        }
    }

    /**
     * Empty current session, fill it with not-logged-in user info.
     *
     * This is intended for installation scripts, unit tests and other
     * special areas. Do NOT use for logout and session termination
     * in normal requests!
     *
     * @param mixed $newsid only used after initialising a user session, is this a new user session?
     */
    public static function init_empty_session(?bool $newsid = null) {
        global $CFG;

        if (isset($GLOBALS['SESSION']->notifications)) {
            // Backup notifications. These should be preserved across session changes until the user fetches and clears them.
            $notifications = $GLOBALS['SESSION']->notifications;
        }
        $GLOBALS['SESSION'] = new \stdClass();
        if (isset($newsid)) {
            $GLOBALS['SESSION']->isnewsessioncookie = $newsid;
        }

        $GLOBALS['USER'] = new \stdClass();
        $GLOBALS['USER']->id = 0;

        if (!empty($notifications)) {
            // Restore notifications.
            $GLOBALS['SESSION']->notifications = $notifications;
        }
        if (isset($CFG->mnet_localhost_id)) {
            $GLOBALS['USER']->mnethostid = $CFG->mnet_localhost_id;
        } else {
            // Not installed yet, the future host id will be most probably 1.
            $GLOBALS['USER']->mnethostid = 1;
        }

        // Link global $USER and $SESSION.
        $_SESSION = array();
        $_SESSION['USER'] =& $GLOBALS['USER'];
        $_SESSION['SESSION'] =& $GLOBALS['SESSION'];
    }

    /**
     * Make sure all cookie and session related stuff is configured properly before session start.
     */
    protected static function prepare_cookies() {
        global $CFG;

        $cookiesecure = is_moodle_cookie_secure();

        if (!isset($CFG->cookiehttponly)) {
            $CFG->cookiehttponly = 1;
        }

        // Set sessioncookie variable if it isn't already.
        if (!isset($CFG->sessioncookie)) {
            $CFG->sessioncookie = '';
        }
        $sessionname = 'MoodleSession'.$CFG->sessioncookie;

        // Make sure cookie domain makes sense for this wwwroot.
        if (!isset($CFG->sessioncookiedomain)) {
            $CFG->sessioncookiedomain = '';
        } else if ($CFG->sessioncookiedomain !== '') {
            $host = parse_url($CFG->wwwroot, PHP_URL_HOST);
            if ($CFG->sessioncookiedomain !== $host) {
                if (substr($CFG->sessioncookiedomain, 0, 1) === '.') {
                    if (!preg_match('|^.*'.preg_quote($CFG->sessioncookiedomain, '|').'$|', $host)) {
                        // Invalid domain - it must be end part of host.
                        $CFG->sessioncookiedomain = '';
                    }
                } else {
                    if (!preg_match('|^.*\.'.preg_quote($CFG->sessioncookiedomain, '|').'$|', $host)) {
                        // Invalid domain - it must be end part of host.
                        $CFG->sessioncookiedomain = '';
                    }
                }
            }
        }

        // Make sure the cookiepath is valid for this wwwroot or autodetect if not specified.
        if (!isset($CFG->sessioncookiepath)) {
            $CFG->sessioncookiepath = '';
        }
        if ($CFG->sessioncookiepath !== '/') {
            $path = parse_url($CFG->wwwroot, PHP_URL_PATH).'/';
            if ($CFG->sessioncookiepath === '') {
                $CFG->sessioncookiepath = $path;
            } else {
                if (strpos($path, $CFG->sessioncookiepath) !== 0 or substr($CFG->sessioncookiepath, -1) !== '/') {
                    $CFG->sessioncookiepath = $path;
                }
            }
        }

        // Discard session ID from POST, GET and globals to tighten security,
        // this is session fixation prevention.
        unset($GLOBALS[$sessionname]);
        unset($_GET[$sessionname]);
        unset($_POST[$sessionname]);
        unset($_REQUEST[$sessionname]);

        // Compatibility hack for non-browser access to our web interface.
        if (!empty($_COOKIE[$sessionname]) && $_COOKIE[$sessionname] == "deleted") {
            unset($_COOKIE[$sessionname]);
        }

        // Set configuration.
        session_name($sessionname);

        $sessionoptions = [
            'lifetime' => 0,
            'path' => $CFG->sessioncookiepath,
            'domain' => $CFG->sessioncookiedomain,
            'secure' => $cookiesecure,
            'httponly' => $CFG->cookiehttponly,
        ];

        if (self::should_use_samesite_none()) {
            // If $samesite is empty, we don't want there to be any SameSite attribute.
            $sessionoptions['samesite'] = 'None';
        }

        session_set_cookie_params($sessionoptions);

        ini_set('session.use_trans_sid', '0');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '0');      // We have custom protection in session init.
        ini_set('session.serialize_handler', 'php');  // We can move to 'php_serialize' after we require PHP 5.5.4 form Moodle.

        // Moodle does normal session timeouts, this is for leftovers only.
        ini_set('session.gc_probability', 1);
        ini_set('session.gc_divisor', 1000);
        ini_set('session.gc_maxlifetime', 60*60*24*4);
    }

    /**
     * Initialise $_SESSION, handles google access
     * and sets up not-logged-in user properly.
     *
     * WARNING: $USER and $SESSION are set up later, do not use them yet!
     *
     * @param bool $newsid is this a new session in first http request?
     */
    protected static function initialise_user_session($newsid) {
        global $CFG;

        $sid = session_id();
        if (!$sid) {
            // No session, very weird.
            error_log('Missing session ID, session not started!');
            self::init_empty_session($newsid);
            return;
        }
        $record = self::get_session_by_sid($sid);
        if (!isset($record->sid)) {
            if (!$newsid) {
                if (!empty($_SESSION['USER']->id)) {
                    // This should not happen, just log it, we MUST not produce any output here!
                    error_log("Cannot find session record $sid for user ".$_SESSION['USER']->id.", creating new session.");
                }
                // Prevent session fixation attacks.
                session_regenerate_id(true);
            }
            $_SESSION = array();
        }
        unset($sid);

        if (isset($_SESSION['USER']->id)) {
            if (!empty($_SESSION['USER']->realuser)) {
                $userid = $_SESSION['USER']->realuser;
            } else {
                $userid = $_SESSION['USER']->id;
            }

            // Verify timeout first.
            $maxlifetime = $CFG->sessiontimeout;
            $timeout = false;
            if (isguestuser($userid) or empty($userid)) {
                // Ignore guest and not-logged in timeouts, there is very little risk here.
                $timeout = false;

            } else if ($record->timemodified < di::get(clock::class)->time() - $maxlifetime) {
                $timeout = true;
                $authsequence = get_enabled_auth_plugins(); // Auths, in sequence.
                foreach ($authsequence as $authname) {
                    $authplugin = get_auth_plugin($authname);
                    if ($authplugin->ignore_timeout_hook($_SESSION['USER'], $record->sid, $record->timecreated, $record->timemodified)) {
                        $timeout = false;
                        break;
                    }
                }
            }

            if ($timeout) {
                if (defined('NO_SESSION_UPDATE') && NO_SESSION_UPDATE) {
                    return;
                }
                session_regenerate_id(true);
                $_SESSION = array();
                self::destroy($record->sid);
            } else {
                // Update session tracking record.
                $update = new \stdClass();
                $updated = false;

                if ($record->userid != $userid) {
                    $update->userid = $record->userid = $userid;
                    $updated = true;
                }

                $ip = getremoteaddr();
                if ($record->lastip != $ip) {
                    $update->lastip = $record->lastip = $ip;
                    $updated = true;
                }

                $time = di::get(clock::class)->time();

                $updatefreq = empty($CFG->session_update_timemodified_frequency) ? 20 : $CFG->session_update_timemodified_frequency;

                if ($record->timemodified == $record->timecreated) {
                    // Always do first update of existing record.
                    $update->timemodified = $record->timemodified = $time;
                    $updated = true;

                } else if ($record->timemodified < $time - $updatefreq) {
                    // Update the session modified flag only once every 20 seconds.
                    $update->timemodified = $record->timemodified = $time;
                    $updated = true;
                }

                if ($updated && (!defined('NO_SESSION_UPDATE') || !NO_SESSION_UPDATE)) {
                    $update->id = $record->id;
                    $update->userid = $record->userid;
                    self::$handler->update_session($update);
                }

                return;
            }
        } else if (isset($record->sid)) {
            // This happens when people switch session handlers...
            session_regenerate_id(true);
            $_SESSION = [];
            self::destroy($record->sid);
        }
        unset($record);

        $timedout = false;
        if (!isset($_SESSION['SESSION'])) {
            $_SESSION['SESSION'] = new \stdClass();
            if (!$newsid) {
                $timedout = true;
            }
        }

        $user = null;

        if (!empty($CFG->opentowebcrawlers)) {
            if (\core_useragent::is_web_crawler()) {
                $user = guest_user();
            }
            $referer = get_local_referer(false);
            if (!empty($CFG->guestloginbutton) and !$user and !empty($referer)) {
                // Automatically log in users coming from search engine results.
                if (strpos($referer, 'google') !== false ) {
                    $user = guest_user();
                } else if (strpos($referer, 'altavista') !== false ) {
                    $user = guest_user();
                }
            }
        }

        // Setup $USER and insert the session tracking record.
        if ($user) {
            self::set_user($user);
            self::add_session($user->id);
        } else {
            self::init_empty_session($newsid);
            self::add_session(0);
        }

        if ($timedout) {
            $_SESSION['SESSION']->has_timed_out = true;
        }
    }

    /**
     * Returns a single session record for this session id.
     *
     * @param string $sid
     * @return \stdClass
     */
    public static function get_session_by_sid(string $sid): \stdClass {
        return self::$handler->get_session_by_sid($sid);
    }

    /**
     * Returns all the session records for this user id.
     *
     * @param int $userid
     * @return array
     */
    public static function get_sessions_by_userid(int $userid): array {
        return self::$handler->get_sessions_by_userid($userid);
    }

    /**
     * Insert new empty session record.
     *
     * @param int $userid
     * @return \stdClass the new record
     */
    public static function add_session(int $userid): \stdClass {
        return self::$handler->add_session($userid);
    }

    /**
     * Update a session record.
     *
     * @param \stdClass $record
     * @return bool
     */
    public static function update_session(\stdClass $record): bool {
        return self::$handler->update_session($record);
    }

    /**
     * Do various session security checks.
     *
     * WARNING: $USER and $SESSION are set up later, do not use them yet!
     * @throws \core\session\exception
     */
    protected static function check_security() {
        global $CFG;

        if (!empty($_SESSION['USER']->id) and !empty($CFG->tracksessionip)) {
            // Make sure current IP matches the one for this session.
            $remoteaddr = getremoteaddr();

            if (empty($_SESSION['USER']->sessionip)) {
                $_SESSION['USER']->sessionip = $remoteaddr;
            }

            if ($_SESSION['USER']->sessionip != $remoteaddr) {
                // This is a security feature - terminate the session in case of any doubt.
                self::terminate_current();
                throw new exception('sessionipnomatch2', 'error');
            }
        }
    }

    /**
     * Login user, to be called from complete_user_login() only.
     * @param \stdClass $user
     */
    public static function login_user(\stdClass $user) {
        global $DB;

        // Regenerate session id and delete old session,
        // this helps prevent session fixation attacks from the same domain.

        $sid = session_id();
        session_regenerate_id(true);
        self::destroy($sid);
        self::add_session($user->id);

        // Let enrol plugins deal with new enrolments if necessary.
        enrol_check_plugins($user);

        // Setup $USER object.
        self::set_user($user);
    }

    /**
     * Returns a valid setting for the SameSite cookie attribute.
     *
     * @return string The desired setting for the SameSite attribute on the cookie. Empty string indicates the SameSite attribute
     * should not be set at all.
     */
    private static function should_use_samesite_none(): bool {
        // We only want None or no attribute at this point. When we have cookie handling compatible with Lax,
        // we can look at checking a setting.

        // Browser support for none is not consistent yet. There are known issues with Safari, and IE11.
        // Things are stablising, however as they're not stable yet we will deal specifically with the version of chrome
        // that introduces a default of lax, setting it to none for the current version of chrome (2 releases before the change).
        // We also check you are using secure cookies and HTTPS because if you are not running over HTTPS
        // then setting SameSite=None will cause your session cookie to be rejected.
        if (\core_useragent::is_chrome() && \core_useragent::check_chrome_version('78') && is_moodle_cookie_secure()) {
            return true;
        }
        return false;
    }

    /**
     * Terminate current user session.
     * @return void
     */
    public static function terminate_current() {
        global $DB;

        if (!self::$sessionactive) {
            self::init_empty_session();
            self::$sessionactive = false;
            return;
        }

        try {
            $DB->delete_records('external_tokens', array('sid'=>session_id(), 'tokentype'=>EXTERNAL_TOKEN_EMBEDDED));
        } catch (\Exception $ignored) {
            // Probably install/upgrade - ignore this problem.
        }

        // Initialize variable to pass-by-reference to headers_sent(&$file, &$line).
        $file = null;
        $line = null;
        if (headers_sent($file, $line)) {
            error_log('Cannot terminate session properly - headers were already sent in file: '.$file.' on line '.$line);
        }

        // Write new empty session and make sure the old one is deleted.
        $sid = session_id();
        session_regenerate_id(true);
        self::destroy($sid);
        self::init_empty_session();
        self::add_session($_SESSION['USER']->id); // Do not use $USER here because it may not be set up yet.
        self::write_close();
    }

    /**
     * No more changes in session expected.
     * Unblocks the sessions, other scripts may start executing in parallel.
     */
    public static function write_close() {
        global $PERF, $ME, $CFG;

        if (self::$sessionactive) {
            $requireslock = self::$handler->requires_write_lock();
            if ($requireslock) {
                // Grab the time when session lock is released.
                $PERF->sessionlock['released'] = microtime(true);
                if (!empty($PERF->sessionlock['gained'])) {
                    $PERF->sessionlock['held'] = $PERF->sessionlock['released'] - $PERF->sessionlock['gained'];
                }
                $PERF->sessionlock['url'] = me();
                self::update_recent_session_locks($PERF->sessionlock);
                self::sessionlock_debugging();
            }

            // If debugging, take a snapshot of session at close and compare on shutdown to detect any accidental mutations.
            if (debugging()) {
                self::$sessionatclose = (array) $_SESSION['SESSION'];
                \core_shutdown_manager::register_function('\core\session\manager::check_mutated_closed_session');
            }

            if (!$requireslock || !self::$requireslockdebug) {
                // Compare the array of the earlier session data with the array now, if
                // there is a difference then a lock is required.
                $arraydiff = self::array_session_diff(
                    self::$priorsession,
                    (array) $_SESSION['SESSION']
                );

                if ($arraydiff) {
                    $error = "Script $ME defined READ_ONLY_SESSION but the following SESSION attributes were changed:";
                    foreach ($arraydiff as $key => $value) {
                        $error .= ' $SESSION->' . $key;
                    }
                    // This will emit an error if debugging is on, even if $CFG->enable_read_only_sessions
                    // is not true as we need to surface this class of errors.
                    error_log($error); // phpcs:ignore
                }
            }
        }

        // More control over whether session data
        // is persisted or not.
        if (self::$sessionactive && session_id()) {
            // Write session and release lock only if
            // indication session start was clean.
            self::$handler->write_close();
        } else {
            // Otherwise, if possible lock exists want
            // to clear it, but do not write session.
            // If the $handler has not been set then
            // there is no session to abort.
            if (isset(self::$handler)) {
                @self::$handler->abort();
            }
        }

        self::$sessionactive = false;
    }

    /**
     * Checks if the session has been mutated since it was closed.
     * In write_close the session is saved to the variable $sessionatclose
     * If there is a difference between $sessionatclose and the current session,
     * it means a script has erroneously closed the session too early.
     * Script is usually called in shutdown_manager
     */
    public static function check_mutated_closed_session() {
        global $ME;

        // Session is still open, mutations are allowed.
        if (self::$sessionactive) {
            return;
        }

        // Detect if session was cleared.
        if (!isset($_SESSION['SESSION']) && isset(self::$sessionatclose)) {
            debugging("Script $ME cleared the session after it was closed.");
            return;
        } else if (!isset($_SESSION['SESSION'])) {
            // Else session is empty, nothing to check.
            return;
        }

        // Session is closed - compare the current session to the session when write_close was called.
        $arraydiff = self::array_session_diff(
            self::$sessionatclose,
            (array) $_SESSION['SESSION']
        );

        if ($arraydiff) {
            $error = "Script $ME mutated the session after it was closed:";
            foreach ($arraydiff as $key => $value) {
                $error .= ' $SESSION->' . $key;

                // Extra debugging for cachestore session changes.
                if (strpos($key, 'cachestore_') === 0 && is_array($value)) {
                    $error .= ': ' . implode(',', array_keys($value));
                }
            }
            debugging($error);
        }
    }

    /**
     * Does the PHP session with given id exist?
     *
     * The session must exist both in session table and actual
     * session backend and the session must not be timed out.
     *
     * Timeout evaluation is simplified, the auth hooks are not executed.
     *
     * @param string $sid
     * @return bool
     */
    public static function session_exists($sid) {
        global $DB, $CFG;

        if (empty($CFG->version)) {
            // Not installed yet, do not try to access database.
            return false;
        }

        // Note: add sessions->state checking here if it gets implemented.
        $record = self::get_session_by_sid($sid);
        if (!isset($record->sid)) {
            return false;
        }

        if (empty($record->userid) or isguestuser($record->userid)) {
            // Ignore guest and not-logged-in timeouts, there is very little risk here.
        } else if ($record->timemodified < di::get(clock::class)->time() - $CFG->sessiontimeout) {
            return false;
        }

        // There is no need the existence of handler storage in public API.
        self::load_handler();
        return self::$handler->session_exists($sid);
    }

    /**
     * Return the number of seconds remaining in the current session.
     * @param string $sid
     */
    public static function time_remaining($sid) {
        global $DB, $CFG;

        if (empty($CFG->version)) {
            // Not installed yet, do not try to access database.
            return ['userid' => 0, 'timeremaining' => $CFG->sessiontimeout];
        }

        // Note: add sessions->state checking here if it gets implemented.
        if (!$record = self::get_session_by_sid($sid)) {
            return ['userid' => 0, 'timeremaining' => $CFG->sessiontimeout];
        }

        if (empty($record->userid) or isguestuser($record->userid)) {
            // Ignore guest and not-logged-in timeouts, there is very little risk here.
            return ['userid' => 0, 'timeremaining' => $CFG->sessiontimeout];
        } else {
            return [
                'userid' => $record->userid,
                'timeremaining' => $CFG->sessiontimeout - (di::get(clock::class)->time() - $record->timemodified),
            ];
        }
    }

    /**
     * Fake last access for given session, this prevents session timeout.
     * @param string $sid
     */
    public static function touch_session($sid) {
        // Timeouts depend on core sessions table only, no need to update anything in external stores.
        self::$handler->update_session((object) [
            'sid' => $sid,
            'timemodified' => di::get(clock::class)->time(),
        ]);
    }

    /**
     * Terminate all sessions unconditionally.
     *
     * @return void
     * @deprecated since Moodle 4.5 See MDL-66161
     * @todo Remove in MDL-81848
     */
    #[\core\attribute\deprecated(
        replacement: 'destroy_all',
        since: '4.5',
    )]
    public static function kill_all_sessions(): void {
        \core\deprecation::emit_deprecation([self::class, __FUNCTION__]);
        self::destroy_all();
    }

    /**
     * Terminate give session unconditionally.
     *
     * @param string $sid
     * @return void
     * @deprecated since Moodle 4.5 See MDL-66161
     * @todo Remove in MDL-81848
     */
    #[\core\attribute\deprecated(
        replacement: 'destroy',
        since: '4.5',
    )]
    public static function kill_session($sid): void {
        \core\deprecation::emit_deprecation([self::class, __FUNCTION__]);
        self::destroy($sid);
    }

    /**
     * Kill sessions of users with disabled plugins.
     *
     * @param string $pluginname
     * @return void
     * @deprecated since Moodle 4.5 See MDL-66161
     * @todo Remove in MDL-81848
     */
    #[\core\attribute\deprecated(
        replacement: 'destroy_by_auth_plugin',
        since: '4.5',
    )]
    public static function kill_sessions_for_auth_plugin(string $pluginname): void {
        \core\deprecation::emit_deprecation([self::class, __FUNCTION__]);
        self::destroy_by_auth_plugin($pluginname);
    }

    /**
     * Terminate all sessions of given user unconditionally.
     *
     * @param int $userid
     * @param string $keepsid keep this sid if present
     * @deprecated since Moodle 4.5 See MDL-66161
     * @todo Remove in MDL-81848
     */
    #[\core\attribute\deprecated(
            replacement: 'destroy_user_sessions',
            since: '4.5',
    )]
    public static function kill_user_sessions($userid, $keepsid = null) {
        \core\deprecation::emit_deprecation([self::class, __FUNCTION__]);
        self::destroy_user_sessions($userid, $keepsid);
    }

    /**
     * Destroy all sessions for a given plugin.
     * Typically used when a plugin is disabled or uninstalled, so all sessions (users) for that plugin are logged out.
     *
     * @param string $pluginname Auth plugin name.
     */
    public static function destroy_by_auth_plugin(string $pluginname): void {
        self::$handler->destroy_by_auth_plugin($pluginname);
    }

    /**
     * Destroy all sessions, and delete all the session data.
     *
     * @return bool
     */
    public static function destroy_all(): bool {
        self::terminate_current();
        self::load_handler();

        try {
            $result = self::$handler->destroy_all();
        } catch (\moodle_exception $ignored) {
            // Do not show any warnings - might be during upgrade/installation.
            $result = true;
        }

         return $result;
    }

    /**
     * Destroy a specific session and delete this session record for this session id.
     *
     * @param string $id
     * @return bool
     */
    public static function destroy(string $id): bool {
        self::load_handler();

        if ($id === session_id()) {
            self::write_close();
        }

        return self::$handler->destroy($id);
    }

    /**
     * Destroy all sessions of given user unconditionally.
     * @param int $userid
     * @param string $keepsid keep this sid if present
     */
    public static function destroy_user_sessions($userid, $keepsid = null) {
        $sessions = self::get_sessions_by_userid($userid);
        foreach ($sessions as $session) {
            if ($keepsid and $keepsid === $session->sid) {
                continue;
            }
            self::destroy($session->sid);
        }
    }

    /**
     * Terminate other sessions of current user depending
     * on $CFG->limitconcurrentlogins restriction.
     *
     * This is expected to be called right after complete_user_login().
     *
     * NOTE:
     *  * Do not use from SSO auth plugins, this would not work.
     *  * Do not use from web services because they do not have sessions.
     *
     * @param int $userid
     * @param string $sid session id to be always keep, usually the current one
     * @return void
     */
    public static function apply_concurrent_login_limit($userid, $sid = null) {
        global $CFG, $DB;

        // NOTE: the $sid parameter is here mainly to allow testing,
        //       in most cases it should be current session id.

        if (isguestuser($userid) or empty($userid)) {
            // This applies to real users only!
            return;
        }

        if (empty($CFG->limitconcurrentlogins) or $CFG->limitconcurrentlogins < 0) {
            return;
        }

        $sessions = self::get_sessions_by_userid($userid);

        $count = count($sessions);

        if ($count <= $CFG->limitconcurrentlogins) {
            return;
        }

        $i = 0;
        if ($sid) {
            foreach ($sessions as $key => $session) {
                if ($session->sid == $sid && $session->userid == $userid) {
                    $i = 1;
                    unset($sessions[$key]);
                }
            }
        }

        // Order records by timecreated DESC.
        usort($sessions, function($a, $b){
            return $b->timecreated <=> $a->timecreated;
        });

        foreach ($sessions as $session) {
            $i++;
            if ($i <= $CFG->limitconcurrentlogins) {
                continue;
            }
            self::destroy($session->sid);
        }
    }

    /**
     * Set current user.
     *
     * @param \stdClass $user record
     */
    public static function set_user(\stdClass $user) {
        global $ADMIN;
        $GLOBALS['USER'] = $user;
        unset($GLOBALS['USER']->description); // Conserve memory.
        unset($GLOBALS['USER']->password);    // Improve security.
        if (isset($GLOBALS['USER']->lang)) {
            // Make sure it is a valid lang pack name.
            $GLOBALS['USER']->lang = clean_param($GLOBALS['USER']->lang, PARAM_LANG);
        }

        // Relink session with global $USER just in case it got unlinked somehow.
        $_SESSION['USER'] =& $GLOBALS['USER'];

        // Nullify the $ADMIN tree global. If we're changing users, then this is now stale and must be generated again if needed.
        $ADMIN = null;

        // Init session key.
        sesskey();

        // Make sure the user is correct in web server access logs.
        set_access_log_user();
    }

    /**
     * Periodic timed-out session cleanup.
     *
     * @param int $maxlifetime Sessions that have not updated for the last max_lifetime seconds will be removed.
     * @return void
     */
    public static function gc(int $maxlifetime = 0): void {
        global $CFG;

        // If max lifetime is not provided, use the default session timeout.
        if ($maxlifetime == 0) {
            $maxlifetime = $CFG->sessiontimeout;
        }
        self::$handler->gc($maxlifetime);
    }

    /**
     * Is current $USER logged-in-as somebody else?
     * @return bool
     */
    public static function is_loggedinas() {
        return !empty($GLOBALS['USER']->realuser);
    }

    /**
     * Returns the $USER object ignoring current login-as session
     * @return \stdClass user object
     */
    public static function get_realuser() {
        if (self::is_loggedinas()) {
            return $_SESSION['REALUSER'];
        } else {
            return $GLOBALS['USER'];
        }
    }

    /**
     * Login as another user - no security checks here.
     * @param int $userid
     * @param \context $context
     * @param bool $generateevent Set to false to prevent the loginas event to be generated
     * @return void
     */
    public static function loginas($userid, \context $context, $generateevent = true) {
        global $USER;

        if (self::is_loggedinas()) {
            return;
        }

        // Switch to fresh new $_SESSION.
        $_SESSION = array();
        $_SESSION['REALSESSION'] = clone($GLOBALS['SESSION']);
        $GLOBALS['SESSION'] = new \stdClass();
        $_SESSION['SESSION'] =& $GLOBALS['SESSION'];

        // Create the new $USER object with all details and reload needed capabilities.
        $_SESSION['REALUSER'] = clone($GLOBALS['USER']);
        $user = get_complete_user_data('id', $userid);
        $user->realuser       = $_SESSION['REALUSER']->id;
        $user->loginascontext = $context;

        // Let enrol plugins deal with new enrolments if necessary.
        enrol_check_plugins($user);

        if ($generateevent) {
            // Create event before $USER is updated.
            $event = \core\event\user_loggedinas::create(
                array(
                    'objectid' => $USER->id,
                    'context' => $context,
                    'relateduserid' => $userid,
                    'other' => array(
                        'originalusername' => fullname($USER, true),
                        'loggedinasusername' => fullname($user, true)
                    )
                )
            );
        }

        // Set up global $USER.
        \core\session\manager::set_user($user);

        if ($generateevent) {
            $event->trigger();
        }

        // Queue migrating the messaging data, if we need to.
        if (!get_user_preferences('core_message_migrate_data', false, $userid)) {
            // Check if there are any legacy messages to migrate.
            if (\core_message\helper::legacy_messages_exist($userid)) {
                \core_message\task\migrate_message_data::queue_task($userid);
            } else {
                set_user_preference('core_message_migrate_data', true, $userid);
            }
        }
    }

    /**
     * Add a JS session keepalive to the page.
     *
     * A JS session keepalive script will be called to update the session modification time every $frequency seconds.
     *
     * Upon failure, the specified error message will be shown to the user.
     *
     * @param string $identifier The string identifier for the message to show on failure.
     * @param string $component The string component for the message to show on failure.
     * @param int $frequency The update frequency in seconds.
     * @param int $timeout The timeout of each request in seconds.
     * @throws \coding_exception IF the frequency is longer than the session lifetime.
     */
    public static function keepalive($identifier = 'sessionerroruser', $component = 'error', $frequency = null, $timeout = 0) {
        global $CFG, $PAGE;

        if ($frequency) {
            if ($frequency > $CFG->sessiontimeout) {
                // Sanity check the frequency.
                throw new \coding_exception('Keepalive frequency is longer than the session lifespan.');
            }
        } else {
            // A frequency of sessiontimeout / 10 matches the timeouts in core/network amd module.
            $frequency = $CFG->sessiontimeout / 10;
        }

        $PAGE->requires->js_call_amd('core/network', 'keepalive', array(
                $frequency,
                $timeout,
                $identifier,
                $component
            ));
    }

    /**
     * Generate a new login token and store it in the session.
     *
     * @return array The current login state.
     */
    private static function create_login_token() {
        global $SESSION;

        $state = [
            'token' => random_string(32),
            'created' => di::get(clock::class)->time(), // Server time - not user time.
        ];

        if (!isset($SESSION->logintoken)) {
            $SESSION->logintoken = [];
        }

        // Overwrite any previous values.
        $SESSION->logintoken[self::$logintokenkey] = $state;

        return $state;
    }

    /**
     * Get the current login token or generate a new one.
     *
     * All login forms generated from Moodle must include a login token
     * named "logintoken" with the value being the result of this function.
     * Logins will be rejected if they do not include this token as well as
     * the username and password fields.
     *
     * @return string The current login token.
     */
    public static function get_login_token() {
        global $CFG, $SESSION;

        $state = false;

        if (!isset($SESSION->logintoken)) {
            $SESSION->logintoken = [];
        }

        if (array_key_exists(self::$logintokenkey, $SESSION->logintoken)) {
            $state = $SESSION->logintoken[self::$logintokenkey];
        }
        if (empty($state)) {
            $state = self::create_login_token();
        }

        // Check token lifespan.
        if ($state['created'] < (di::get(clock::class)->time() - $CFG->sessiontimeout)) {
            $state = self::create_login_token();
        }

        // Return the current session login token.
        if (array_key_exists('token', $state)) {
            return $state['token'];
        } else {
            return false;
        }
    }

    /**
     * Check the submitted value against the stored login token.
     *
     * @param mixed $token The value submitted in the login form that we are validating.
     *                     If false is passed for the token, this function will always return true.
     * @return boolean If the submitted token is valid.
     */
    public static function validate_login_token($token = false) {
        global $CFG, $SESSION;

        if (!empty($CFG->alternateloginurl) || !empty($CFG->disablelogintoken)) {
            // An external login page cannot generate the login token we need to protect CSRF on
            // login requests.
            // Other custom login workflows may skip this check by setting disablelogintoken in config.
            return true;
        }
        if ($token === false) {
            // authenticate_user_login is a core function was extended to validate tokens.
            // For existing uses other than the login form it does not
            // validate that a token was generated.
            // Some uses that do not validate the token are login/token.php,
            // or an auth plugin like auth/ldap/auth.php.
            return true;
        }

        $currenttoken = self::get_login_token();

        // We need to clean the login token so the old one is not valid again.
        unset($SESSION->logintoken);

        if ($currenttoken !== $token) {
            // Fail the login.
            return false;
        }
        return true;
    }

    /**
     * Get the recent session locks array.
     *
     * @return array Recent session locks array.
     */
    public static function get_recent_session_locks() {
        global $SESSION;

        if (!isset($SESSION->recentsessionlocks)) {
            // This will hold the pages that blocks other page.
            $SESSION->recentsessionlocks = array();
        }

        return $SESSION->recentsessionlocks;
    }

    /**
     * Updates the recent session locks.
     *
     * This function will store session lock info of all the pages visited.
     *
     * @param array $sessionlock Session lock array.
     */
    public static function update_recent_session_locks($sessionlock) {
        global $CFG, $SESSION;

        if (empty($CFG->debugsessionlock)) {
            return;
        }

        $readonlysession = defined('READ_ONLY_SESSION') && READ_ONLY_SESSION;
        $readonlydebugging = !empty($CFG->enable_read_only_sessions) || !empty($CFG->enable_read_only_sessions_debug);
        if ($readonlysession && $readonlydebugging) {
            return;
        }

        $SESSION->recentsessionlocks = self::get_recent_session_locks();
        array_push($SESSION->recentsessionlocks, $sessionlock);

        self::cleanup_recent_session_locks();
    }

    /**
     * Reset recent session locks array if there is a time gap more than SESSION_RESET_GAP_THRESHOLD.
     */
    public static function cleanup_recent_session_locks() {
        global $SESSION;

        $locks = self::get_recent_session_locks();

        if (count($locks) > self::MAXIMUM_STORED_SESSION_HISTORY) {
            // Keep the last MAXIMUM_STORED_SESSION_HISTORY locks and ignore the rest.
            $locks = array_slice($locks, -1 * self::MAXIMUM_STORED_SESSION_HISTORY);
        }

        if (count($locks) > 2) {
            for ($i = count($locks) - 1; $i > 0; $i--) {
                // Calculate the gap between session locks.
                $gap = $locks[$i]['released'] - $locks[$i - 1]['start'];
                if ($gap >= self::SESSION_RESET_GAP_THRESHOLD) {
                    // Remove previous locks if the gap is 1 second or more.
                    $SESSION->recentsessionlocks = array_slice($locks, $i);
                    break;
                }
            }
        }
    }

    /**
     * Get the page that blocks other pages at a specific timestamp.
     *
     * Look for a page whose lock was gained before that timestamp, and released after that timestamp.
     *
     * @param  float $time Time before session lock starts.
     * @return array|null
     */
    public static function get_locked_page_at($time) {
        $recentsessionlocks = self::get_recent_session_locks();
        foreach ($recentsessionlocks as $recentsessionlock) {
            if ($time >= $recentsessionlock['gained'] &&
                $time <= $recentsessionlock['released']) {
                return $recentsessionlock;
            }
        }
    }

    /**
     * Display the page which blocks other pages.
     *
     * @return string
     */
    public static function display_blocking_page() {
        global $PERF;

        $page = self::get_locked_page_at($PERF->sessionlock['start']);
        $output = "Script ".me()." was blocked for ";
        $output .= number_format($PERF->sessionlock['wait'], 3);
        if ($page != null) {
            $output .= " second(s) by script: ";
            $output .= $page['url'];
        } else {
            $output .= " second(s) by an unknown script.";
        }

        return $output;
    }

    /**
     * Get session lock info of the current page.
     *
     * @return array
     */
    public static function get_session_lock_info() {
        global $PERF;

        if (!isset($PERF->sessionlock)) {
            return null;
        }
        return $PERF->sessionlock;
    }

    /**
     * Display debugging info about slow and blocked script.
     */
    public static function sessionlock_debugging() {
        global $CFG, $PERF;

        if (!empty($CFG->debugsessionlock)) {
            if (isset($PERF->sessionlock['held']) && $PERF->sessionlock['held'] > $CFG->debugsessionlock) {
                debugging("Script ".me()." locked the session for ".number_format($PERF->sessionlock['held'], 3)
                ." seconds, it should close the session using \core\session\manager::write_close().", DEBUG_NORMAL);
            }

            if (isset($PERF->sessionlock['wait']) && $PERF->sessionlock['wait'] > $CFG->debugsessionlock) {
                $output = self::display_blocking_page();
                debugging($output, DEBUG_DEVELOPER);
            }
        }
    }

    /**
     * Compares two arrays and outputs the difference.
     *
     * Note - checking between objects and array type is only done at the top level.
     * Any changes in types below the top level will not be detected.
     * However, if their values are the same, they will be treated as equal.
     *
     * Any changes, such as removals, edits or additions will be detected.
     *
     * @param array $previous
     * @param array $current
     * @return array
     */
    private static function array_session_diff(array $previous, array $current): array {
        // To use array_udiff_uassoc, the first array must have the most keys; this ensures every key is checked.
        // To do this, we first need to sort them by the length of their keys.
        $arrays = [$current, $previous];

        // Sort them by the length of their keys.
        usort($arrays, function ($a, $b) {
            return count(array_keys($b)) - count(array_keys($a));
        });

        // The largest is the first value in the $arrays, after sorting.
        // The smallest is then the last one.
        // If they are the same size, it does not matter which is which.
        $largest = $arrays[0];
        $smallest = $arrays[1];

        // Defines a function that casts the values to arrays.
        // This is so the properties are compared, instead any object's identities.
        $casttoarray = function ($value) {
            return json_decode(json_encode($value), true);
        };

        // Defines a function that compares all keys by their string value.
        $keycompare = function ($a, $b) {
            return strcmp($a, $b);
        };

        // Defines a function that compares all values by first their type, and then their values.
        // If the value contains any objects, they are cast to arrays before comparison.
        $valcompare = function ($a, $b) use ($casttoarray) {
            // First compare type.
            // If they are not the same type, they are definitely not the same.
            // Note we do not check types recursively.
            if (gettype($a) !== gettype($b)) {
                return 1;
            }

            // Next compare value. Cast any objects to arrays to compare their properties,
            // instead of the identitiy of the object itself.
            $v1 = $casttoarray($a);
            $v2 = $casttoarray($b);

            if ($v1 !== $v2) {
                return 1;
            }

            return 0;
        };

        // Apply the comparison functions to the two given session arrays,
        // making sure to use the largest array first, so that all keys are considered.
        return array_udiff_uassoc($largest, $smallest, $valcompare, $keycompare);
    }
}
