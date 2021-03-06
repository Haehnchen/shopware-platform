import { Component, Mixin, State } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-settings-snippet-list.html.twig';
import './sw-settings-snippet-list.scss';

Component.register('sw-settings-snippet-list', {
    template,

    inject: ['snippetSetService', 'snippetService'],

    mixins: [
        Mixin.getByName('sw-settings-list')
    ],

    data() {
        return {
            entityName: 'snippets',
            snippetSets: {},
            grid: [],
            metaId: '',
            resetItems: [],
            hasResetableItems: true,
            filterItems: [],
            isCustomState: false,
            emptySnippets: false,
            appliedFilter: [],
            emptyIcon: this.$route.meta.$module.icon
        };
    },

    computed: {
        snippetSetStore() {
            return State.getStore('snippet_set');
        },
        queryIdCount() {
            return this.queryIds.length;
        },
        metaName() {
            return this.snippetSets[0].name;
        }
    },

    methods: {
        getList() {
            this.initializeSnippetSet();
        },

        initializeSnippetSet() {
            if (!this.$route.query.ids || this.$route.query.ids.length <= 0) {
                this.$router.back();

                return;
            }

            this.isLoading = true;
            this.queryIds = this.$route.query.ids;
            const criteria = CriteriaFactory.equalsAny('id', this.queryIds);

            this.snippetService.getFilter().then((response) => {
                this.filterItems = response.data;
            });

            const filter = {
                isCustom: this.isCustomState,
                emptySnippets: this.emptySnippets,
                term: this.term,
                namespaces: this.appliedFilter,
                authors: [],
                translationKeys: []
            };

            this.snippetSetService.getCustomList(this.page, this.limit, filter).then((response) => {
                this.snippetSetStore.getList({ criteria }).then((sets) => {
                    this.snippetSets = sets.items;
                    this.metaId = this.queryIds[0];
                    this.total = response.total;

                    this.grid = this.prepareGrid(response.data);
                }).then(() => {
                    this.isLoading = false;
                });
            });
        },

        prepareGrid(grid) {
            function prepareContent(items) {
                const content = items.reduce((acc, item) => {
                    acc[item.setId] = item;
                    return acc;
                }, {});
                content.id = items[0].translationKey;
                return content;
            }

            return Object.values(grid).reduce((accumulator, items) => {
                accumulator.push(prepareContent(items));
                return accumulator;
            }, []);
        },

        onEdit(snippet) {
            if (snippet && snippet.id) {
                this.$router.push({
                    name: 'sw.settings.snippet.detail',
                    params: {
                        id: snippet.id
                    }
                });
            }
        },

        onInlineEditSave(result) {
            const responses = [];
            const key = result[this.metaId].translationKey;

            this.snippetSets.forEach((item) => {
                if (result[item.id].value === null) {
                    result[item.id].value = result[item.id].origin;
                }

                if (result[item.id].origin !== result[item.id].value) {
                    responses.push(this.snippetService.save(result[item.id]));
                } else if (result[item.id].id !== null) {
                    responses.push(this.snippetService.delete(result[item.id].id));
                }
            });

            Promise.all(responses).then(() => {
                this.inlineSaveSuccessMessage(key);
                this.getList();
            }).catch(() => {
                this.inlineSaveErrorMessage(key);
                this.getList();
            });
        },

        onEmptyClick() {
            this.isCustomState = false;
            this.getList();
        },


        inlineSaveSuccessMessage(key) {
            const titleSaveSuccess = this.$tc('sw-settings-snippet.list.titleSaveSuccess');
            const messageSaveSuccess = this.$tc(
                'sw-settings-snippet.list.messageSaveSuccess',
                this.queryIdCount,
                { key }
            );

            this.createNotificationSuccess({
                title: titleSaveSuccess,
                message: messageSaveSuccess
            });
        },

        inlineSaveErrorMessage(key) {
            const titleSaveError = this.$tc('sw-settings-snippet.list.titleSaveError');
            const messageSaveError = this.$tc(
                'sw-settings-snippet.list.messageSaveError',
                this.queryIdCount,
                { key }
            );

            this.createNotificationError({
                title: titleSaveError,
                message: messageSaveError
            });
        },

        onReset(item) {
            const keys = Object.keys(item).filter((name) => { return name !== 'id'; });
            const criteria = CriteriaFactory.equalsAny('id', keys);
            this.isLoading = true;

            this.snippetSetStore.getList({ criteria }).then((response) => {
                const resetItems = [];
                Object.values(item).forEach((currentItem, index) => {
                    if (!(currentItem instanceof Object)) {
                        return;
                    }

                    currentItem.setName = this.getName(response.items, currentItem.setId);
                    if (currentItem.id === null) {
                        currentItem.id = index;
                        currentItem.isFileSnippet = true;
                    }

                    resetItems.push(currentItem);
                });

                this.resetItems = resetItems;
                this.showDeleteModal = item;
            }).finally(() => {
                this.isLoading = false;
            });
        },

        getName(list, id) {
            let name = '';
            list.forEach((item) => {
                if (item.id === id) {
                    name = item.name;
                }
            });

            return name;
        },

        onSelectionChanged(selection) {
            this.selection = selection;
            this.selectionCount = Object.keys(selection).length;
            this.hasResetableItems = this.selectionCount === 0;
        },

        onConfirmReset() {
            const items = Object.values(this.selection);
            this.showDeleteModal = false;

            items.forEach((item) => {
                if (item.hasOwnProperty('isFileSnippet')) {
                    return;
                }
                this.isLoading = true;
                this.snippetService.delete(item.id).then(() => {
                    this.createSuccessMessage(item);
                }).catch(() => {
                    this.createResetErrorNote(item);
                }).finally(() => {
                    this.isLoading = false;
                    this.getList();
                });
            });
        },

        createSuccessMessage(item) {
            const title = this.$tc('sw-settings-snippet.list.titleDeleteSuccess');
            const message = this.$tc(
                'sw-settings-snippet.list.resetSuccessMessage',
                0,
                {
                    key: item.value,
                    value: item.origin
                }
            );

            this.createNotificationSuccess({
                title,
                message
            });
        },

        createResetErrorNote(item) {
            const title = this.$tc('sw-settings-snippet.list.titleSaveError');
            const message = this.$tc(
                'sw-settings-snippet.list.resetErrorMessage',
                0,
                { key: item.value }
            );

            this.createNotificationError({
                title,
                message
            });
        },

        onSearch(term) {
            this.term = term;
            this.page = 1;
            this.initializeSnippetSet();
        },

        onInlineEditCancel(rowItems) {
            Object.keys(rowItems).forEach((itemKey) => {
                const item = rowItems[itemKey];

                item.value = item.resetTo;
            });
        },

        onChange(field) {
            this.page = 1;

            if (field.name === 'customSnippets') {
                this.isCustomState = field.value;
                this.initializeSnippetSet();
                return;
            }

            if (field.name === 'emptySnippets') {
                this.emptySnippets = field.value;
                this.initializeSnippetSet();
                return;
            }

            if (field.value) {
                if (this.appliedFilter.indexOf(field.name) !== -1) {
                    return;
                }

                this.appliedFilter.push(field.name);
                this.initializeSnippetSet();
                return;
            }

            this.appliedFilter.splice(this.appliedFilter.indexOf(field.name), 1);
            this.initializeSnippetSet();
        }
    }
});
