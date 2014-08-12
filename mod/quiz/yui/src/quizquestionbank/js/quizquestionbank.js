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
 * Add questions from question bank functionality for a popup in quiz editing page.
 *
 * @package   mod_quiz
 * @copyright 2014 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


var CSS = {
        QBANKFORM: 'div.questionbankformforpopup',
        QBANKLINK: 'a.questionbank',
        QBANK: '.questionbank'
};

var PARAMS = {
    PAGE: 'addonpage',
    HEADER: 'header',
};

var POPUP = function() {
    POPUP.superclass.constructor.apply(this, arguments);
};

Y.extend(POPUP, Y.Base, {
    qbank: Y.one(CSS.QBANK),
    qbankform: Y.one(CSS.QBANKFORM),
    qbanklink: Y.all(CSS.QBANKLINK),

    dialogue: function(header, body, hideshow) {
        // Create a dialogue on the page and hide it.
        config = {
            headerContent : header,
            bodyContent : body,
            draggable : true,
            modal : true,
            zIndex : 1000,
            context: [CSS.QBANK, 'tr', 'br', ['beforeShow']],
            centered: false,
            width: null,
            visible: false,
            postmethod: 'form',
            footerContent: null,
            extraClasses: ['mod_quiz_qbank_dialogue']
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
        var header = this.qbank._node.getAttribute(PARAMS.HEADER);
        var body = this.qbankform;

        this.dialogue(header, body, 'hide');

        this.qbanklink.each(function(node) {
            var page = node.getAttribute(PARAMS.PAGE);
            header = node.getAttribute(PARAMS.HEADER);
            node.on('click', this.display_dialog, this, header, page, body);
        }, this);
    },

    display_dialog : function (e, header, page, body) {
        e.preventDefault();
        this.dialogue(header, body, 'show');
    }
});

M.mod_quiz = M.mod_quiz || {};
M.mod_quiz.quizquestionbank = M.mod_quiz.quizquestionbank || {};
M.mod_quiz.quizquestionbank.init = function() {
    return new POPUP();
};
