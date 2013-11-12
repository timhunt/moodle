YUI.add('moodle-mod_quiz-quizbase', function(Y) {

    /**
     * The quizbase class
     */
    var QUIZBASENAME = 'mod_quiz-quizbase';

    var QUIZBASE = function() {
        QUIZBASE.superclass.constructor.apply(this, arguments);
    }

    Y.extend(QUIZBASE, Y.Base, {
        // Registered Modules
        registermodules : [],

        /**
         * Initialize the quizbase module
         */
        initializer : function(config) {
            // We don't actually perform any work here
        },

        /**
         * Register a new Javascript Module
         *
         * @param object The instantiated module to call functions on
         */
        register_module : function(object) {
            this.registermodules.push(object);
        },

        /**
         * Invoke the specified function in all registered modules with the given arguments
         *
         * @param functionname The name of the function to call
         * @param args The argument supplied to the function
         */
        invoke_function : function(functionname, args) {
            for (module in this.registermodules) {
                if (functionname in this.registermodules[module]) {
                    this.registermodules[module][functionname](args);
                }
            }
        }
    },
    {
        NAME : QUIZBASENAME,
        ATTRS : {}
    }
    );

    // Ensure that M.mod_quiz exists and that quizbase is initialised correctly
    M.mod_quiz = M.mod_quiz || {};
    M.mod_quiz.quizbase = M.mod_quiz.quizbase || new QUIZBASE();

    // Abstract functions that needs to be defined per feature (quiz/yui/feature/feature.js)
    M.mod_quiz.edit = M.mod_quiz.edit || {}

   /**
    * Swap section (should be defined in format.js if requred)
    *
    * @param {YUI} Y YUI3 instance
    * @param {string} node1 node to swap to
    * @param {string} node2 node to swap with
    * @return {NodeList} section list
    */
    M.mod_quiz.edit.swap_sections = M.mod_quiz.edit.swap_sections || function(Y, node1, node2) {
        return null;
    }

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
    M.mod_quiz.edit.process_sections = M.mod_quiz.edit.process_sections || function(Y, sectionlist, response, sectionfrom, sectionto) {
        return null;
    }

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
        }
    }

   /**
    * Get section list for this format (usually items inside container_node.container_class selector)
    *
    * @param {YUI} Y YUI3 instance
    * @return {string} section selector
    */
    M.mod_quiz.edit.get_section_selector = M.mod_quiz.edit.get_section_selector || function(Y) {
        var config = M.mod_quiz.edit.get_config();
        if (config.section_node && config.section_class) {
            return config.section_node + '.' + config.section_class;
        }
        console.log('section_node and section_class are not defined in M.mod_quiz.edit.get_config');
        return null;
    }

   /**
    * Get section wraper for this format (only used in case when each
    * container_node.container_class node is wrapped in some other element).
    *
    * @param {YUI} Y YUI3 instance
    * @return {string} section wrapper selector or M.mod_quiz.edit.get_section_selector
    * if section_wrapper_node and section_wrapper_class are not defined in the format config.
    */
    M.mod_quiz.edit.get_section_wrapper = M.mod_quiz.edit.get_section_wrapper || function(Y) {
        var config = M.mod_quiz.edit.get_config();
        if (config.section_wrapper_node && config.section_wrapper_class) {
            return config.section_wrapper_node + '.' + config.section_wrapper_class;
        }
        return M.mod_quiz.edit.get_section_selector(Y);
    }

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
            console.log('container_node is not defined in M.mod_quiz.edit.get_config');
        }
    }

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
            console.log('container_class is not defined in M.mod_quiz.edit.get_config');
        }
    }

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
    }

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
    }

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
            console.log('section_node is not defined in M.mod_quiz.edit.get_config');
        }
    }

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
            console.log('section_class is not defined in M.mod_quiz.edit.get_config');
        }

    }

},
'@VERSION@', {
    requires : ['base', 'node']
}
);
