/**
 * The quizbase class to provide shared functionality to Modules within Moodle.
 *
 * @module moodle-mod_quiz-quizbase
 */
var QUIZBASENAME = 'mod_quiz-quizbase';

var QUIZBASE = function() {
    QUIZBASE.superclass.constructor.apply(this, arguments);
};

/**
 * The coursebase class to provide shared functionality to Modules within
 * Moodle.
 *
 * @class M.course.coursebase
 * @constructor
 */
Y.extend(QUIZBASE, Y.Base, {
    // Registered Modules
    registermodules : [],

    /**
     * Register a new Javascript Module
     *
     * @method register_module
     * @param {Object} The instantiated module to call functions on
     * @chainable
     */
    register_module : function(object) {
        this.registermodules.push(object);

        return this;
    },

    /**
     * Invoke the specified function in all registered modules with the given arguments
     *
     * @method invoke_function
     * @param {String} functionname The name of the function to call
     * @param {mixed} args The argument supplied to the function
     * @chainable
     */
    invoke_function : function(functionname, args) {
        var module;
        for (module in this.registermodules) {
            if (functionname in this.registermodules[module]) {
                this.registermodules[module][functionname](args);
            }
        }

        return this;
    }
}, {
    NAME : QUIZBASENAME,
    ATTRS : {}
});

// Ensure that M.course exists and that coursebase is initialised correctly
M.mod_quiz = M.mod_quiz || {};
M.mod_quiz.quizbase = M.mod_quiz.quizbase || new QUIZBASE();

// Abstract functions that needs to be defined per format (course/format/somename/format.js)
M.mod_quiz.edit = M.mod_quiz.edit || {};

/**
 * Swap section (should be defined in format.js if requred)
 *
 * @param {YUI} Y YUI3 instance
 * @param {string} node1 node to swap to
 * @param {string} node2 node to swap with
 * @return {NodeList} section list
 */
M.mod_quiz.edit.swap_sections = M.mod_quiz.edit.swap_sections || function() {
    return null;
};

/**
 * Process sections after ajax response (should be defined in format.js)
 * If some response is expected, we pass it over to format, as it knows better
 * hot to process it.
 *
 * @param {YUI} Y YUI3 instance
 * @param {NodeList} list of sections
 * @param {array} response ajax response
 * @param {string} sectionfrom first affected section
 * @param {string} sectionto last affected section
 * @return void
 */
M.mod_quiz.edit.process_sections = M.mod_quiz.edit.process_sections || function() {
    return null;
};

/**
* Get sections config for this format, for examples see function definition
* in the formats.
*
* @return {object} section list configuration
*/
M.mod_quiz.edit.get_config = M.mod_quiz.edit.get_config || function() {
    return {
        container_node : null, // compulsory
        container_class : null, // compulsory
        section_wrapper_node : null, // optional
        section_wrapper_class : null, // optional
        section_node : null,  // compulsory
        section_class : null  // compulsory
    };
};

/**
 * Get section list for this format (usually items inside container_node.container_class selector)
 *
 * @param {YUI} Y YUI3 instance
 * @return {string} section selector
 */
M.mod_quiz.edit.get_section_selector = M.mod_quiz.edit.get_section_selector || function() {
    var config = M.mod_quiz.edit.get_config();
    if (config.section_node && config.section_class) {
        return config.section_node + '.' + config.section_class;
    }
    Y.log('section_node and section_class are not defined in M.mod_quiz.edit.get_config', 'warn', 'moodle-mod_quiz-quizbase');
    return null;
};

/**
 * Get section wraper for this format (only used in case when each
 * container_node.container_class node is wrapped in some other element).
 *
 * @param {YUI} Y YUI3 instance
 * @return {string} section wrapper selector or M.mod_quiz.format.get_section_selector
 * if section_wrapper_node and section_wrapper_class are not defined in the format config.
 */
M.mod_quiz.edit.get_section_wrapper = M.mod_quiz.edit.get_section_wrapper || function(Y) {
    var config = M.mod_quiz.edit.get_config();
    if (config.section_wrapper_node && config.section_wrapper_class) {
        return config.section_wrapper_node + '.' + config.section_wrapper_class;
    }
    return M.mod_quiz.edit.get_section_selector(Y);
};

/**
 * Get the tag of container node
 *
 * @return {string} tag of container node.
 */
M.mod_quiz.edit.get_containernode = M.mod_quiz.edit.get_containernode || function() {
    var config = M.mod_quiz.edit.get_config();
    if (config.container_node) {
        return config.container_node;
    } else {
        Y.log('container_node is not defined in M.mod_quiz.edit.get_config', 'warn', 'moodle-mod_quiz-quizbase');
    }
};

/**
 * Get the class of container node
 *
 * @return {string} class of the container node.
 */
M.mod_quiz.edit.get_containerclass = M.mod_quiz.edit.get_containerclass || function() {
    var config = M.mod_quiz.edit.get_config();
    if (config.container_class) {
        return config.container_class;
    } else {
        Y.log('container_class is not defined in M.mod_quiz.edit.get_config', 'warn', 'moodle-mod_quiz-quizbase');
    }
};

/**
 * Get the tag of draggable node (section wrapper if exists, otherwise section)
 *
 * @return {string} tag of the draggable node.
 */
M.mod_quiz.edit.get_sectionwrappernode = M.mod_quiz.edit.get_sectionwrappernode || function() {
    var config = M.mod_quiz.edit.get_config();
    if (config.section_wrapper_node) {
        return config.section_wrapper_node;
    } else {
        return config.section_node;
    }
};

/**
 * Get the class of draggable node (section wrapper if exists, otherwise section)
 *
 * @return {string} class of the draggable node.
 */
M.mod_quiz.edit.get_sectionwrapperclass = M.mod_quiz.edit.get_sectionwrapperclass || function() {
    var config = M.mod_quiz.edit.get_config();
    if (config.section_wrapper_class) {
        return config.section_wrapper_class;
    } else {
        return config.section_class;
    }
};

/**
 * Get the tag of section node
 *
 * @return {string} tag of section node.
 */
M.mod_quiz.edit.get_sectionnode = M.mod_quiz.edit.get_sectionnode || function() {
    var config = M.mod_quiz.edit.get_config();
    if (config.section_node) {
        return config.section_node;
    } else {
        Y.log('section_node is not defined in M.mod_quiz.edit.get_config', 'warn', 'moodle-mod_quiz-quizbase');
    }
};

/**
 * Get the class of section node
 *
 * @return {string} class of the section node.
 */
M.mod_quiz.edit.get_sectionclass = M.mod_quiz.edit.get_sectionclass || function() {
    var config = M.mod_quiz.edit.get_config();
    if (config.section_class) {
        return config.section_class;
    } else {
        Y.log('section_class is not defined in M.mod_quiz.edit.get_config', 'warn', 'moodle-mod_quiz-quizbase');
    }
};
