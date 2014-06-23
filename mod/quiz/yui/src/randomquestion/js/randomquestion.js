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


/**
 * Add a random question functionality for a popup in quiz editing page.
 *
 * @package   mod_quiz
 * @copyright 2014 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

var CSS = {
    RQCONTAINER: '.cm-edit-action .addarandomquestion',
    RANDOMQUESTIONLINK: 'a.addarandomquestion',
    RANDOMQUESTION: '.addarandomquestion',
    RANDOMQUESTIONDIALOG: '#randomquestiondialog',
    RANDOMQUESTIONFORM: 'form.randomquestionform'
};


var PARAMS = {
    CMID: 'cmid',
    ID: 'id',
    PAGE: 'addonpage',
    HEADER: 'header',
    FORM: 'form'
};

var POPUP = function() {
    POPUP.superclass.constructor.apply(this, arguments);
};

Y.extend(POPUP, Y.Base, {
    rq: Y.one(CSS.RANDOMQUESTION),
    page: 0,
    header: 'header',
    body: 'body',

    initializer : function() {
        Y.all(CSS.RANDOMQUESTIONLINK).each(function(node) {
            node.on('click', this.display_dialog, this);
        }, this);
    },

    display_dialog : function (e) {
        e.preventDefault();

        this.page = this.rq._node.getAttribute(PARAMS.PAGE);
        this.header = this.rq._node.getAttribute(PARAMS.HEADER);
        this.body = this.rq._node.getAttribute(PARAMS.FORM);

        var config = {
            headerContent : this.header,
            bodyContent : this.body,
            draggable : true,
            modal : true,
            zIndex : 1000,
            context: [CSS.REPAGINATECOMMAND, 'tr', 'br', ['beforeShow']],
            centered: false,
            width: '80%',
            visible: false,
            postmethod: 'form',
            footerContent: null
        };

        var popup = { dialog: null };
        popup.dialog = new M.core.dialogue(config);
        popup.dialog.show();
    }
});

M.mod_quiz = M.mod_quiz || {};
M.mod_quiz.randomquestion = M.mod_quiz.randomquestion || {};
M.mod_quiz.randomquestion.init = function() {
    return new POPUP();
};
