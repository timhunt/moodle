var SELECTORS = {
    CREATENEWQUESTION: 'div.createnewquestion',
    CREATENEWQUESTIONFORM: 'div.createnewquestion form',
    CHOOSERDIALOGUE: 'div.chooserdialogue',
    CHOOSERHEADER: 'div.choosertitle'
};

function Chooser() {
    Chooser.superclass.constructor.apply(this, arguments);
}

Y.extend(Chooser, M.core.chooserdialogue, {
    initializer: function() {
        Y.delegate('click', this.displayQuestionChooser, 'body',
                '.core_question_add_question_action', this);
    },

    displayQuestionChooser: function(e) {
        var dialogue = Y.one(SELECTORS.CREATENEWQUESTION + ' ' + SELECTORS.CHOOSERDIALOGUE),
            header = Y.one(SELECTORS.CREATENEWQUESTION + ' ' + SELECTORS.CHOOSERHEADER);

        if (this.container === null) {
            // Setup the dialogue, and then prepare the chooser if it's not already been set up.
            this.setup_chooser_dialogue(dialogue, header, {});
            this.prepare_chooser();
        }

        // Update all of the hidden fields for this particular instance of the chooser.
        this.dataToHiddenField(e.target, 'category');
        this.dataToHiddenField(e.target, 'cmid');
        this.dataToHiddenField(e.target, 'courseid');
        this.dataToHiddenField(e.target, 'returnurl');
        this.dataToHiddenField(e.target, 'appendqnumstring');
        this.dataToHiddenField(e.target, 'scrollpos');

        // Display the chooser dialogue.
        this.display_chooser(e);
    },

    dataToHiddenField: function(dataSource, name) {
        var hidden = this.container.one('input[type="hidden"][name="' + name + '"]'),
            value  = dataSource.getData(name);
        if (value) {
            if (!hidden) {
                this.container.one('form').append('<input type="hidden" name="' + name + '">');
                hidden = this.container.one('input[type="hidden"][name="' + name + '"]');
            }
            hidden.set('value', value);
        } else if (hidden) {
            hidden.remove();
        }
    }
}, {
    NAME: 'questionChooser'
});

M.question = M.question || {};
M.question.init_chooser = function(config) {
    return new Chooser(config);
};
