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

    /**
     * Set up the chooser dialogue.
     *
     * @method initializer
     */
    initializer: function() {
        Y.delegate('click', this.displayQuestionChooser, 'body',
                '.core_question_add_question_action', this);
    },

    /**
     * Prepare the chooser for display following a click on a specific button/link.
     *
     * @method displayQuestionChooser
     * @param {Event} the event that is triggering the chooser being shown.
     */
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

    /**
     * Copy one particular parameter from the element that was activated to
     * display the chooser, into a hidden field in the chooser. Or, if that
     * data attribute is missing or blank, remove the corresponding hidden field.
     *
     * @method dataToHiddenField
     * @param {Node} dataSource Node that was activated, and which has the parameters.
     * @param {String} name the name of the data attribute that should be synched
     *      with a corresponding hidden field.
     */
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
