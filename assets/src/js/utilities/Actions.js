/**
 * Action Processor
 *
 * You can process actions manually by calling the following:
 *
 * ```js
 * Snowboard.addPlugin('actions', Actions);
 * Snowboard.actions().doActions(actions, event);
 * ```
 *
 * @copyright 2023 Winter.
 * @author Luke Towers <wintercms@luketowers.ca>
 */
export default class Actions extends window.Snowboard.Singleton {
    /**
     * @TODO: This is terrible, find a better way to manage the available actions
     */
    construct() {
        window.actions = window.actions || {};
        window.actions = {
            ...(window.actions || {}),

            open_url: (data, event) => {
                if (typeof data.target === "undefined") {
                    data.target = "_self";
                }

                window.open(data.href, data.target);
            }
        };
    }
    /**
     * Run the provided actions.
     *
     * @param {array} actions
     * @param {Object} event
     */
    async doActions(actions, event) {
        if (event) {
            event.stopPropagation();
        }

        if (!Array.isArray(actions)) {
            console.error(`Actions is not an array`);
            return;
        }

        actions.forEach((action) => {
            // @TODO: Terrible, find a better way to handle dynamically registering available actions
            if (typeof window.actions[action.action] !== 'function') {
                console.error(`Action ${action.action} does not exist on the window object`);
                return;
            }

            window.actions[action.action](action.data, event);
        });
    }
}
