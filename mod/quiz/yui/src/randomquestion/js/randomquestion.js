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
        RANDOMQUESTIONFORM: 'div.randomquestionformforpopup',
        RANDOMQUESTIONLINK: 'a.addarandomquestion',
        RANDOMQUESTION: '.addarandomquestion'
};

var PARAMS = {
    PAGE: 'addonpage',
    HEADER: 'header',
    FORM: 'form'
};

var POPUP = function() {
    POPUP.superclass.constructor.apply(this, arguments);
};

Y.extend(POPUP, Y.Base, {
    rq: Y.one(CSS.RANDOMQUESTION),
    rqlink: Y.all(CSS.RANDOMQUESTIONLINK),
    page: 0,
    header: 'header',
    body: 'body',

    dialogue: function(header, body, hideshow) {
        // Create a dialogue on the page and hide it.
        config = {
            headerContent : header,
            bodyContent : body,
            draggable : true,
            modal : true,
            zIndex : 1000,
            context: [CSS.RANDOMQUESTIONLINK, 'tr', 'br', ['beforeShow']],
            centered: false,
            width: 'auto',
            visible: false,
            postmethod: 'form',
            footerContent: null
        };
        var popup = { dialog: null };
        popup.dialog = new M.core.dialogue(config);
        if (hideshow === 'hide') {
            popup.dialog.hide();
        } else {
            popup.dialog.show();
        }
    },
 
    initializer : function() {
        this.dialogue(Y.one(CSS.RANDOMQUESTION)._node.getAttribute(PARAMS.HEADER), Y.one(CSS.RANDOMQUESTIONFORM), 'hide');
        this.rqlink.each(function(node) {
            var formid = node.getAttribute(PARAMS.PAGE);
            node.on('click', this.display_dialog, this, formid);
        }, this);
    },

    display_dialog : function (e, formid) {
        e.preventDefault();
        var rq = Y.one('li#page-' + formid + ' ' + CSS.RANDOMQUESTIONLINK);
        this.header = rq._node.getAttribute(PARAMS.HEADER);
        var body = Y.one(CSS.RANDOMQUESTIONFORM);

        var formparampage = Y.one(CSS.RANDOMQUESTIONFORM + ' ' +  'input#rform_qpage');
        formparampage.set('value', formid);

        this.dialogue(this.header, body, 'show');
    }
});

M.mod_quiz = M.mod_quiz || {};
M.mod_quiz.randomquestion = M.mod_quiz.randomquestion || {};
M.mod_quiz.randomquestion.init = function() {
    return new POPUP();
};
