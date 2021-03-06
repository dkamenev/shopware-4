/**
 * TinyMCE WYSIWYG editor plugin - Media Selection
 *
 * This plugin gives the user the ability to open up the media
 * selection, select one or more media files and add them directly into
 * the editor.
 *
 * The plugins injects the selected media directly to the current cursor position,
 * so the user wouldn't be interrupted in their workflow.
 *
 * shopware AG (c) 2012. All rights reserved.
 *
 * @class Provides a new ui control element to open up the media selection
 * @link http://www.shopware.de/
 * @author st.pohl <stp@shopware.de>
 * @date 2012-03-14
 * @license http://www.shopware.de/license
 * @package tinymce-plugins
 * @subpackage media-selection
 */
(function()
/** @lends tinymce# */
{

    // Create the tinymce plugin
    tinymce.create('tinymce.plugins.MediaSelectionPlugin', {

        /**
         * Holds the local tinymce editor instance
         * @null
         */
        ed: null,

        /**
         * CSS class for images
         * @string
         */
        imageCls: 'tinymce-editor-image',

        /**
         * CSS class for links
         * @string
         */
        linkCls: 'tinymce-editor-link',

        /**
         * CSS class for videos
         * @string
         */
        videoCls: 'tinymce-editor-video',

        /**
         * CSS class for audio tracks
         * @string
         */
        audioCls: 'tinymce-editor-audio',


        /**
         * Initializes the plugin, this will be executed after the plugin has been created.
         * This call is done before the editor instance has finished it's initialization so use the onInit event
         * of the editor instance to intercept that event.
         *
         * Could raise an error if the ExtJS application doesn't support sub applications which is necessary when
         * we're dealing if the Shopware.apps.MediaManager
         *
         * @public
         * @param [tinymce.Editor] ed - Editor instance that the plugin is initialized in.
         * @param [string] url -  Absolute URL to where the plugin is located.
         * @return void
         */
        init : function(ed, url) {
            var me = this;

            me.ed = ed;

            // Register the command so that it can be invoked
            // by using tinyMCE.activeEditor.execCommand('mceMediaSelection');
            ed.addCommand('mceMediaSelection', function() {
                if(typeof(Shopware.app.Application.addSubApplication) !== 'function') {

                    Ext.Error.raise({
                        msg: 'Your ExtJS application does not support sub applications',
                        option: [ ed, url ]
                    });
                }

                // Opens the media selection and registers a callback method to process the incoming image(s)
                Shopware.app.Application.addSubApplication({
                    name: 'Shopware.apps.MediaManager',
                    layout: 'small',
                    selectionMode: 'single',
                    eventScope: me,
                    mediaSelectionCallback: me._processImage
                });
            });

            // Register media selection button
            ed.addButton('media_selection', {
                title : 'Media Selection',
                cls: 'tinymce-media-selection',
                cmd : 'mceMediaSelection',
                image : url + '/icons/inbox-image.png'
            });
        },

        /**
         * Returns information about the plugin as a name/value array.
         * The current keys are longname, author, authorurl, infourl and version.
         *
         * @public
         * @return [object] Name/value array containing information about the plugin.
         */
        getInfo : function() {
            return {
                longname : 'MediaManager - MediaSelection',
                author : 'shopware AG - st.pohl',
                authorurl : 'http://www.shopware.de',
                infourl : 'http://wiki.shopware.de/',
                version : '1.0.0'
            };
        },

        /**
         * Processes the selection in the media selection window
         *
         * @private
         * @param [Ext.button.Button] btn - Pressed button in the media selection window
         * @return void
         */
        _processImage: function(btn) {
            var me = this,
                ed = me.ed,
                win = btn.up('window'),
                dataPnl = win.down('.mediamanager-media-view'),
                dataView = dataPnl.dataView,
                selModel = dataView.getSelectionModel(),
                selected = selModel.getSelection();

            // Loop through the selection and add the images to the editor
            Ext.each(selected, function(record) {
                var type = record.get('type');

                if(type === 'VIDEO') {
                    me._insertVideo(record);
                } else if(type === 'IMAGE') {
                    me._insertImage(record);
                } else if(type === 'MUSIC') {
                    me._insertAudio(record);
                } else {
                    me._insertLink(record);
                }

                ed.undoManager.add();
            });

            win.destroy();
        },

        /**
         * Inserts an image to the current cursor position in the editor.
         *
         * Note that the original size will be included.
         *
         * @private
         * @param [object] record - Ext.data.Model
         * @return [boolean]
         */
        _insertImage: function(record) {
            var me = this,
                ed = me.ed,
                settings = ed.settings, path,
                args;

            if(settings.document_base_url.length && settings.relative_urls == false) {
                path = settings.document_base_url + record.get('path');
            }else
                path = record.get('path');

            args = {
                'class': me.imageCls,
                width: record.get('width'),
                height: record.get('height'),
                alt: record.get('name'),
                src: path
            };

            ed.execCommand('mceInsertContent', false, tinymce.DOM.createHTML('img', args), { skip_undo : 1 });

            return true;
        },

        /**
         * Inserts a link to the current cursor position in the editor.
         *
         * Note that the "target"-attribute is always "_blank" to open
         * up the link in a new browser window.
         *
         * @private
         * @param [object] record - Ext.data.Model
         * @return [boolean]
         */
        _insertLink: function(record) {
            var me = this,
                ed = me.ed,
                args;

            args = {
                // Class is a special word in Webkit, so we need to quote the word
                'class': me.linkCls,
                href: record.get('path'),
                title: record.get('name'),
                target: '_blank'
            };

            ed.execCommand('mceInsertContent', false, tinymce.DOM.createHTML('a', args, record.get('name')), { skip_undo : 1 });

            return true;
        },

        /**
         * Inserts a video to the current cursor position in the editor.
         *
         * Note that you're browser needs to support the HTML5 video-tag
         *
         * @private
         * @param [object] record - Ext.data.Model
         * @return [boolean]
         */
        _insertVideo: function(record) {
            var me = this,
                ed = me.ed,
                args;

            args = {
                // Class is a special word in Webkit, so we need to quote the word
                'class': me.videoCls,
                width: 320,
                height: 240,
                src:  ed.settings.document_base_url + record.get('path'),
                controls: true
            };

            ed.execCommand('mceInsertContent', false, tinymce.DOM.createHTML('video', args), { skip_undo : 1 });

            return true;
        },

        /**
         * Inserts an audio track to the current cursor position in the editor.
         *
         * Note that you're browser needs to support the HTML5 audio-tag
         *
         * @private
         * @param [object] record - Ext.data.Model
         * @return [boolean]
         */
        _insertAudio: function(record) {
            var me = this,
                ed = me.ed,
                args;

            args = {
                // Class is a special word in Webkit, so we need to quote the word
                'class': me.audioCls,
                src:  ed.settings.document_base_url + record.get('path'),
                controls: true
            };

            ed.execCommand('mceInsertContent', false, tinymce.DOM.createHTML('audio', args), { skip_undo : 1 });

            return true;
        }
    });

    // Register the plugin
    tinymce.PluginManager.add('media_selection', tinymce.plugins.MediaSelectionPlugin);
})();