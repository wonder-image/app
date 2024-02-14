function setSearchInput() {

    document.querySelectorAll("[data-wi-search='true']").forEach(element => {

        element.addEventListener("keyup", inputSearch);
        element.addEventListener("change", lengthCount);
        element.addEventListener("focusin", lengthCount);
        element.addEventListener("focusout", lengthCount);

        if (element.dataset.wiSearchUrl != undefined) { setDynamicSearch(element); }

    });
    
}

function setUploader() {

    document.querySelectorAll("[data-wi-uploader]").forEach(uploader => {

        var id = uploader.id;
        var type = uploader.dataset.wiUploader;
        var dir = uploader.dataset.wiDir;
        var value = uploader.dataset.wiValue;
        var label = uploader.dataset.wiUploaderLabel;
        var accept = uploader.accept;

        if (accept === '' || accept === '*') {
            var validation = false;
        } else {
            var validation = true;
        }

        var files = [];
        
        if (value != "") {

            var value = JSON.parse(value);
            
            value.forEach(file => {
                
                var source = {
                    source: dir+file
                };

                files.push(source);

            });

        }

        if (type == 'classic') {

            FilePond.create(uploader, {
                storeAsFile: true,
                labelIdle: `Trascina `+label+` o <span class="filepond--label-action">Cerca</span>`,
                files: files,
                allowFileTypeValidation: validation,
                onaddfile: () => { check(); },
                onremovefile: () => { check(); }
            });
            
        } else if (type == 'profile') {

            FilePond.create(uploader, {
                storeAsFile: true,
                labelIdle: `Trascina `+label+` o <span class="filepond--label-action">Cerca</span>`,
                imageCropAspectRatio: '1:1',
                stylePanelLayout: 'compact circle',
                styleLoadIndicatorPosition: 'center bottom',
                styleButtonRemoveItemPosition: 'center bottom',
                files: files,
                allowFileTypeValidation: validation,
                onaddfile: () => { check(); },
                onremovefile: () => { check(); }
            });
            
        }

    });

}

function setTextarea() {

    document.querySelectorAll("[data-wi-textarea]").forEach(textarea => {

        var container = textarea.parentElement;
        var type = textarea.dataset.wiTextarea;

        var textareaId = textarea.id;
        var textareaValue = atob(textarea.dataset.wiValue);

        // Creo l'editor
        var editorId = textareaId+'_editor';

        var editor = document.createElement("div");
        editor.id = editorId;
        editor.classList.add('position-relative');
        editor.classList.add('float-start');
        editor.classList.add('w-100');
        editor.classList.add('h-auto');
        editor.classList.add('border');
        editor.style.maxHeight = '250px';

        if (type == 'base' || type == 'plus' || type == 'pro') {

            // Quill.js

            editor.classList.add('border-top-0');
            editor.classList.add('rounded-bottom');
    
            container.after(editor);

            var toolbarId = editorId+'_toolbar';

            if (type == 'base') {
                var option =  [['bold', 'italic', 'underline', 'strike'], ['clean']];
            } else if (type == 'plus') {
                var option =  [['bold', 'italic', 'underline', 'strike'], ['link'], ['clean']];
            } else if (type == 'pro') {
                var option =  [['bold', 'italic', 'underline', 'strike'], ['link'], [{ list: 'ordered' }, { list: 'bullet' }], ['clean']];
            }

            new Quill('#'+editorId, {
                theme: 'snow',
                modules: {
                    toolbar: option
                }
            });

            var col = container.parentElement;
            var toolbar = col.querySelector('.ql-toolbar');

            toolbar.id = toolbarId;
            toolbar.classList.add('position-relative');
            toolbar.classList.add('float-start');
            toolbar.classList.add('w-100');
            toolbar.classList.add('border');
            toolbar.classList.add('rounded-top');

            var editor = document.querySelector('#'+editorId+' .ql-editor');

            editor.innerHTML = textareaValue.replace("'", "\'");

            editor.addEventListener('keydown', () => { textarea.value = editor.innerHTML; });
            editor.addEventListener('DOMNodeInserted', () => { textarea.value = editor.innerHTML; });

        } else if (type == 'table' || type == 'blog') {

            // Editor.js

            EDITORJS_TOOLS_BLOG.image.config.additionalRequestHeaders.dir = textarea.dataset.wiFolder+'/images';
            EDITORJS_TOOLS_BLOG.gallery.config.additionalRequestHeaders.dir = textarea.dataset.wiFolder+'/images';
            EDITORJS_TOOLS_BLOG.attaches.config.additionalRequestHeaders.dir = textarea.dataset.wiFolder+'/files';

            editor.classList.add('wi-'+type);
            editor.classList.add('pt-3');
            editor.classList.add('rounded');
            editor.classList.add('overflow-scroll');

            container.after(editor);

            if (type == 'table') {
                
                if (textareaValue == null || textareaValue == '') {
                    var data = '{"blocks":[{"id":"O3rAzVD6s8","type":"table","data":{"withHeadings":true,"content":[]}}]}';
                } else {
                    var data = '{"blocks":'+textareaValue+'}';
                }

                var tools = EDITORJS_TOOLS_TABLE;

            } else if (type == 'blog') {

                editor.style.maxHeight = '500px';

                if (textareaValue == null || textareaValue == '') {
                    var data = '{}';
                } else {
                    var data = '{"blocks":'+textareaValue+'}';
                }

                var tools = EDITORJS_TOOLS_BLOG;
                
            }

            new EditorJS({
                tools: tools,
                holder: editorId, 
                autofocus: false,
                data: JSON.parse(data),
                onChange: (api) => {

                    api.saver.save().then((outputData) => {
                        var editorValue = JSON.stringify(outputData.blocks);
                        document.getElementById(textareaId).value = editorValue;
                    }).catch((error) => {
                        console.log('Saving failed: ', error)
                    });

                },
                i18n: EDITORJS_i18n_IT
            });

        }

    });

}

function setJsTree() {
            
    document.querySelectorAll('[data-wi-tree]').forEach(element => {

        var container = element.parentElement;

        var type = element.dataset.wiTree;
        var searchBar = container.querySelector('[data-wi-search="true"]');

        var Tree = $(element).jstree({
            core: {
                check_callback: false
            },
            checkbox : {
                keep_selected_style: false,
                three_state: false,
            },
            types : {
                default : {
                    icon : "bi bi-folder"
                }
            },
            plugins : [ "checkbox", "types", "search" ]
        });

        Tree.jstree('open_all');

        if (searchBar != undefined) {
            $(searchBar).keyup(function () {
                var v = $(searchBar).val();
                $(Tree).jstree(true).search(v);
            });
        }

        $(element).on('click', '.jstree-anchor', function(e) {

            var selectedNodes = $(element).jstree().get_checked();

            container.querySelectorAll('input[type="checkbox"]:checked').forEach(element => { element.checked = false; });

            if (type == 'radio') {

                if (selectedNodes.length > 1) {
                    $(element).jstree().uncheck_node(selectedNodes[0]);
                }

                var selectedNodes = $(element).jstree().get_checked();

            }

            selectedNodes.forEach(element => { container.querySelector('input[value="'+element+'"]').checked = true; });

            check();

        });

    });

}

function setCheckBoolean() {
    
    document.querySelectorAll('[data-wi-check-boolean]').forEach(element => {

        var inputTrue = element.querySelector('input.wi-true');
        var labelTrue = inputTrue.labels[0];
        var inputFalse = element.querySelector('input.wi-false');
        var labelFalse = inputFalse.labels[0];

        inputTrue.addEventListener("change", (el) => {

            var checked = el.target.checked;

            if (checked) { 
                inputFalse.checked = false; 
                labelFalse.classList.remove('btn-primary'); 
                labelTrue.classList.add('btn-primary'); 
            } else {
                labelTrue.classList.remove('btn-primary'); 
            }

        });

        inputFalse.addEventListener("change", (el) => {

            var checked = el.target.checked;

            if (checked) { 
                inputTrue.checked = false; 
                labelTrue.classList.remove('btn-primary'); 
                labelFalse.classList.add('btn-primary'); 
            } else {
                labelFalse.classList.remove('btn-primary'); 
            }

        });


    });

}