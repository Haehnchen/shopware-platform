import { Component, Mixin, State } from 'src/core/shopware';
import template from './sw-condition-base.html.twig';
import './sw-condition-base.scss';

const PLACEHOLDER_NAME = 'placeholder';

/**
 * @public
 * @description TODO: Add description
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-base :condition="condition"></sw-condition-base>
 */
Component.register('sw-condition-base', {
    template,

    inject: ['ruleConditionDataProviderService'],
    mixins: [
        Mixin.getByName('validation'),
        Mixin.getByName('notification')
    ],

    /**
     * All additional passed attributes are bound explicit to the correct child element.
     */
    inheritAttrs: false,

    props: {
        condition: {
            type: Object,
            required: false,
            default() {
                return {};
            }
        },
        conditionAssociations: {
            type: Object,
            required: true
        }
    },

    computed: {
        fieldNames() {
            return [];
        },
        conditionClass() {
            return '';
        },
        defaultValues() {
            return {};
        },
        errorStore() {
            return State.getStore('error');
        },
        disableContextDeleteButton() {
            const parent = this.conditionAssociations.getById(this.condition.parentId);

            return this.condition.type === PLACEHOLDER_NAME
                   && parent.children.length === 1;
        }
    },

    data() {
        return {
            formErrors: {},
            hasErrors: false,
            rulePageComponent: undefined
        };
    },

    created() {
        this.createdComponent();
    },
    beforeMount() {
        this.applyDefaultValues();
    },

    mounted() {
        this.mountComponent();
    },

    methods: {
        checkErrors() {
            const values = Object.values(this.formErrors);
            this.hasErrors = values.length && values.filter(error => error.detail.length > 0).length;
        },
        mountComponent() {
            if (!this.condition.value) {
                this.condition.value = {};
            }

            Object.keys(this.condition.value).forEach((key) => {
                if (!this.fieldNames.includes(key)) {
                    delete this.condition.value[key];
                }
            });

            const keys = Object.keys(this.condition.value);
            this.fieldNames.forEach((fieldName) => {
                if (!keys.includes(fieldName)) {
                    this.condition.value[fieldName] = undefined;
                }
            });

            const fieldNames = this.fieldNames;
            fieldNames.push('type');

            fieldNames.forEach(fieldName => {
                const boundExpression = `rule.conditions.${this.condition.id}.${fieldName}`;
                this.formErrors[fieldName] = this.errorStore.registerFormField(boundExpression);
            });

            this.$children.forEach(child => {
                if (!this.fieldNames.includes(child.$attrs.name)) {
                    return;
                }

                child.$on('input', () => { this.deleteError(child.$attrs.name); });
            });

            this.deleteError('type');
        },

        deleteError(fieldName) {
            if (!this.formErrors[fieldName].detail) {
                return;
            }

            this.errorStore.deleteError(this.formErrors[fieldName]);
            this.checkErrors();
        },

        getLabel(type) {
            return this.ruleConditionDataProviderService.getByType(type).label;
        },
        createdComponent() {
            if (!this.condition.value) {
                this.condition.value = {};
            }

            let parent = this.$parent;

            while (parent) {
                if (['sw-settings-rule-create', 'sw-settings-rule-detail'].includes(parent.$options.name)) {
                    this.rulePageComponent = parent;
                    break;
                }

                parent = parent.$parent;
            }

            if (this.rulePageComponent) {
                this.rulePageComponent.$on('on-save-rule', () => { this.checkErrors(); });
            }
        },
        applyDefaultValues() {
            Object.keys(this.defaultValues).forEach(key => {
                if (typeof this.condition.value[key] === 'undefined') {
                    this.condition.value[key] = this.defaultValues[key];
                }
            });
        }
    }
});
