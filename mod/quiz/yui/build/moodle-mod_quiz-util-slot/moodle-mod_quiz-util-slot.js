YUI.add('moodle-mod_quiz-util-slot', function (Y, NAME) {

/**
 * A collection of utility classes for use with slots.
 *
 * @module moodle-mod_quiz-util
 * @submodule moodle-mod_quiz-util-slot
 */

Y.namespace('Moodle.mod_quiz.util.slot');

/**
 * A collection of utility classes for use with slots.
 *
 * @class Moodle.mod_quiz.util.slot
 * @static
 */
Y.Moodle.mod_quiz.util.slot = {
    CONSTANTS: {
        SLOTIDPREFIX : 'slot-'
    },
    SELECTORS: {
        SLOT: '.activity',
        INSTANCENAME: '.instancename'
    },

    /**
     * Retrieve the slot item from one of it's child Nodes.
     *
     * @method getSlotFromComponent
     * @param slotcomponent {Node} The component Node.
     * @return {Node|null} The Slot Node.
     */
    getSlotFromComponent: function(slotcomponent) {
        return Y.one(slotcomponent).ancestor(this.SELECTORS.SLOT, true);
    },

    /**
     * Determines the slot ID for the provided slot.
     *
     * @method getId
     * @param slot {Node} The slot to find an ID for.
     * @return {Number|false} The ID of the slot in question or false if no ID was found.
     */
    getId: function(slot) {
        // We perform a simple substitution operation to get the ID.
        var id = slot.get('id').replace(
                this.CONSTANTS.SLOTIDPREFIX, '');

        // Attempt to validate the ID.
        id = parseInt(id, 10);
        if (typeof id === 'number' && isFinite(id)) {
            return id;
        }
        return false;
    },

    /**
     * Determines the slot name for the provided slot.
     *
     * @method getName
     * @param slot {Node} The slot to find a name for.
     * @return {string|false} The name of the slot in question or false if no ID was found.
     */
    getName: function(slot) {
        var instance = slot.one(this.SELECTORS.INSTANCENAME);
        if (instance) {
            return instance.get('firstChild').get('data');
        }
        return null;
    }
};


}, '@VERSION@', {"requires": ["node", "moodle-mod_quiz-util-base"]});
