import template from './sw-product-variants-delivery-listing.html.twig';
import './sw-product-variants-delivery-listing.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-product-variants-delivery-listing', {
    template,

    props: {
        product: {
            type: Object,
            required: true
        },

        selectedGroups: {
            type: Array,
            required: true
        }
    },

    computed: {
        listingModeOptions() {
            return [
                {
                    value: 'single',
                    name: this.$tc('sw-product.variations.deliveryModal.listingLabelModeSingle')
                },
                {
                    value: 'expanded',
                    name: this.$tc('sw-product.variations.deliveryModal.listingLabelModeExpanded')
                }
            ];
        },

        mainVariant() {
            return this.product.mainVariant;
        },

        variantCriteria() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.equals('product.parentId', this.product.id));

            return criteria;
        },

        context() {
            const context = { ...Shopware.Context.api, inheritance: true };

            return context;
        },

        selectedGroupsSorted() {
            // prepare group sorting
            let sortedGroups = [];
            const selectedGroupsCopy = [...this.selectedGroups];

            // check if sorting exists on server
            if (this.product.configuratorGroupConfig && this.product.configuratorGroupConfig.length > 0) {
                // add server sorting to the sortedGroups
                sortedGroups = this.product.configuratorGroupConfig.reduce((acc, configGroup) => {
                    const relatedGroup = selectedGroupsCopy.find(group => group.id === configGroup.id);

                    if (relatedGroup) {
                        acc.push(relatedGroup);

                        // remove from orignal array
                        selectedGroupsCopy.splice(selectedGroupsCopy.indexOf(relatedGroup), 1);
                    }

                    return acc;
                }, []);
            }

            // add non sorted groups at the end of the sorted array
            sortedGroups = [...sortedGroups, ...selectedGroupsCopy];

            return sortedGroups;
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            const listingMode = this.product.mainVariant ? 'single' : 'expanded';

            this.updateListingMode(listingMode);
        },

        updateListingMode(value) {
            this.product.listingMode = value;
        },

        updateMainVariant(value) {
            this.product.mainVariant = value;
        },

        isActiveGroupInListing(groupId) {
            if (!this.product.configuratorGroupConfig) {
                return false;
            }

            const activeGroupConfig = this.product.configuratorGroupConfig.find((group) => {
                return group.id === groupId;
            });

            return activeGroupConfig ? activeGroupConfig.expressionForListings : false;
        },

        onChangeGroupListing(value, groupId) {
            const configuratorGroupConfig = this.product.configuratorGroupConfig || [];
            const existingGroup = configuratorGroupConfig.find((group) => group.id === groupId);

            if (existingGroup) {
                existingGroup.expressionForListings = value;

                return;
            }

            this.product.configuratorGroupConfig.push({
                id: groupId,
                expressionForListings: value,
                representation: 'box'
            });
        },

        isActiveListingMode(mode) {
            return mode === this.product.listingMode;
        },

        isDisabledListingMode(mode) {
            return !this.isActiveListingMode(mode);
        }
    }
});