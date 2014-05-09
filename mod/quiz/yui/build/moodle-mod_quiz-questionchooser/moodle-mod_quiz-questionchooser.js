YUI.add('moodle-mod_quiz-questionchooser', function (Y, NAME) {

var SELECTORS = {
    ADDNEWQUESTIONBUTTON: 'a.addquestion',
    ADDNEWQUESTIONFORM: 'form.addnewquestion',
    CREATENEWQUESTION: 'div.createnewquestion',
    CREATENEWQUESTIONFORM: 'div.createnewquestion form',
    CHOOSERDIALOGUE: 'div.chooserdialogue',
    CHOOSERHEADER: 'div.choosertitle'
};

/**
 * The questionchooser class  is responsible for instantiating and displaying the question chooser
 * when viewing a quiz in editing mode.
 *
 * @class questionchooser
 * @constructor
 * @protected
 * @extends M.core.chooserdialogue
 */
var QUESTIONCHOOSER = function() {
    QUESTIONCHOOSER.superclass.constructor.apply(this, arguments);
};

Y.extend(QUESTIONCHOOSER, M.core.chooserdialogue, {
    initializer: function() {
        Y.all(SELECTORS.ADDNEWQUESTIONBUTTON).each(function(node) {
                node.on('click', this.display, this);
        }, this);
    },
    display: function(e) {
        e.preventDefault();
        var dialogue = Y.one(SELECTORS.CREATENEWQUESTION + ' ' + SELECTORS.CHOOSERDIALOGUE),
            header = Y.one(SELECTORS.CREATENEWQUESTION + ' ' + SELECTORS.CHOOSERHEADER);

        if (this.container === null) {
            // Setup the dialogue, and then prepare the chooser if it's not already been set up.
            this.setup_chooser_dialogue(dialogue, header, {});
            this.prepare_chooser();
        }

        // Update all of the hidden fields within the questionbank form.
        var originForm = e.target.ancestor(Y.Moodle.mod_quiz.util.page.SELECTORS.PAGE, true).one(SELECTORS.ADDNEWQUESTIONFORM),
            targetForm = this.container.one('form'),
            hiddenElements = originForm.all('input[type="hidden"]');

        targetForm.all('input.customfield').remove();
        hiddenElements.each(function(field) {
            targetForm.appendChild(field.cloneNode())
                .removeAttribute('id')
                .addClass('customfield');
        });

        // Display the chooser dialogue.
        this.display_chooser(e);
    }
}, {
    NAME: 'mod_quiz-questionchooser'
});

M.mod_quiz = M.mod_quiz || {};
M.mod_quiz.init_questionchooser = function(config) {
    M.mod_quiz.question_chooser = new QUESTIONCHOOSER(config);
    return M.mod_quiz.question_chooser;
};


}, '@VERSION@', {"requires": ["moodle-core-chooserdialogue"]});
