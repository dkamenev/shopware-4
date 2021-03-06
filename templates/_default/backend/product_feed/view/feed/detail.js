/**
 * Shopware 4.0
 * Copyright © 2012 shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 *
 * @category   Shopware
 * @package    ProductFeed
 * @subpackage View
 * @copyright  Copyright (c) 2012, shopware AG (http://www.shopware.de)
 * @version    $Id$
 * @author shopware AG
 */
//{namespace name=backend/product_feed/view/feed}
/**
 * Shopware UI - product feed detail main window.
 *
 * Displays all Detail product feed Information
 */
//{block name="backend/product_feed/view/feed/detail"}
Ext.define('Shopware.apps.ProductFeed.view.feed.Detail', {
    extend:'Ext.container.Container',
    alias:'widget.product_feed-feed-detail',
    border: 0,
    bodyPadding: 10,
    layout: 'column',
    autoScroll:true,
    defaults: {
        columnWidth: 0.5
    },
    //Text for the ModusCombobox
    variantExportData:[
        [1, '{s name=detail_general/variant_export_data/no}No{/s}'],
        [2, '{s name=detail_general/variant_export_data/variant}Variants{/s}']
    ],

    /**
     * Initialize the Shopware.apps.ProductFeed.view.feed.detail and defines the necessary
     * default configuration
     */
    initComponent:function () {
        var me = this;

        me.items = [ me.createGeneralFormLeft(), me.createGeneralFormRight() ];

        me.callParent(arguments);
    },
    /**
     * creates all fields for the general form on the left side
     */
    createGeneralFormLeft:function () {
        var me = this;

        return Ext.create('Ext.container.Container', {
            layout: 'anchor',
            style: 'padding: 0 10px 0 0',
            defaults:{
                anchor:'100%',
                labelStyle:'font-weight: 700;',
                xtype:'textfield'
            },
            items:[
                {
                    fieldLabel:'{s name=detail_general/field/title}Title{/s}',
                    name:'name',
                    allowBlank:false,
                    required:true,
                    enableKeyEvents:true
                },
                {
                    fieldLabel:'{s name=detail_general/field/file_name}File name{/s}',
                    name:'fileName',
                    allowBlank:false,
                    required:true,
                    enableKeyEvents:true
                },
                {
                    fieldLabel:'{s name=detail_general/field/partner_id}Partner ID{/s}',
                    name:'partnerId',
                    helpText:'{s name=detail_general/field/partner_id/help}The partner ID will be attached to the corresponding link. So when a customer will buy an article the direct connection to the partner is saved. {/s}',
                    enableKeyEvents:true
                },
                {
                    fieldLabel:'{s name=detail_general/field/hash}Hash{/s}',
                    name:'hash',
                    helpText:'{s name=detail_general/field/hash/help}The Hash will be generated automatically. This value is shown in the URL of the generated product feed file. If you change this value the price portal is not able to access the feed.{/s}',
                    enableKeyEvents:true
                },
                {
                    xtype:'checkbox',
                    fieldLabel:'{s name=detail_general/field/active}Active{/s}',
                    inputValue:1,
                    uncheckedValue:0,
                    name:'active'
                },
                {
                    xtype:'combobox',
                    name:'variantExport',
                    fieldLabel:'{s name=detail_general/field/variantExport}Export variants{/s}',
                    store:new Ext.data.SimpleStore({
                        fields:['id', 'text'], data:this.variantExportData
                    }),
                    valueField:'id',
                    displayField:'text',
                    mode:'local',
                    allowBlank:false,
                    required:true,
                    editable:false
                }
            ]
        });
    },

    /**
     * creates all fields for the general form on the right side
     */
    createGeneralFormRight: function () {
        var me = this;

        var currencyStore = Ext.create('Shopware.apps.Base.store.Currency').load();
        return Ext.create('Ext.container.Container', {
            layout: 'anchor',
            style: 'padding: 0 0 0 10px',
            defaults:{
                anchor:'100%',
                labelStyle:'font-weight: 700;',
                xtype:'combobox'
            },
            items:[
                {
                    name:'shopId',
                    fieldLabel:'{s name=detail_general/field/shop}Shop{/s}',
                    store: Ext.create('Shopware.store.Shop').load(),
                    valueField: 'id',
                    helpText:'{s name=detail_general/field/shop/help}The URLs/domains for the Article and Imagelinks will be changed based on this value{/s}',
                    displayField: 'name'
                },
                {
                    name:'customerGroupId',
                    fieldLabel:'{s name=detail_general/field/customer_group}Customer group{/s}',
                    store: Ext.create('Shopware.store.CustomerGroup').load(),
                    valueField:'id',
                    helpText:'{s name=detail_general/field/customergroup/help}Defines the customer group the prices are taken out of{/s}',
                    displayField:'name'
                },
                {
                    name:'languageId',
                    fieldLabel:'{s name=detail_general/field/language}Language{/s}',
                    store: me.shopStore.load(),
                    valueField: 'id',
                    emptyText: '{s name=detail_general/language_combo_box/standard}Standard{/s}',
                    helpText:'{s name=detail_general/field/language_id/help}The export language{/s}',
                    displayField: 'name'
                },
                {
                    name:'currencyId',
                    fieldLabel:'{s name=detail_general/field/currency}Currency{/s}',
                    store: currencyStore,
                    helpText:'{s name=detail_general/field/currency/help}The export is based on the selected currency{/s}',
                    valueField: 'id',
                    displayField: 'name'
                },
                {
                    xtype:'combotree',
                    name:'categoryId',
                    valueField: 'id',
                    forceSelection: false,
                    editable: true,
                    displayField: 'name',
                    treeField: 'categoryId',
                    fieldLabel:'{s name=detail_general/field/category}Category{/s}',
                    helpText:'{s name=detail_general/field/category/help}This will execute the export for the selected category only{/s}',
                    store: me.comboTreeCategoryStore,
                    selectedRecord : me.record
                }

            ]
        });
    }
});
//{/block}
