function setSearchInput() {

    document.querySelectorAll("[data-wi-search='true']").forEach(element => {

        element.addEventListener("keyup", inputSearch);
        element.addEventListener("change", lengthCount);
        element.addEventListener("focusin", lengthCount);
        element.addEventListener("focusout", lengthCount);

        if (element.dataset.wiSearchUrl != undefined) { setDynamicSearch(element); }

    });
    
}

function setTextarea() {

    document.querySelectorAll("[data-wi-textarea]").forEach(textarea => {

        var container = textarea.parentElement;
        var type = textarea.dataset.wiTextarea;

        var textareaId = textarea.id;
        var textareaValue = textarea.value;

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

                var tools = {
                    paragraph: false,
                    inlineCode: InlineCode,
                    table: {
                        class: Table,
                        inlineToolbar: [ 'bold', 'link' ]
                    },
                    textAlign: TextAlign
                };

            } else {

                editor.style.maxHeight = '500px';

                if (textareaValue == null || textareaValue == '') {
                    var data = '{}';
                } else {
                    var data = '{"blocks":'+textareaValue+'}';
                }

                var tools = {
                    header: {
                        class: Header,
                        inlineToolbar: true,
                        config: {
                          placeholder: 'Titolo...',
                          levels: [1, 2, 4],
                          defaultLevel: 2
                        }
                    },
                    quote: {
                        class: Quote,
                        inlineToolbar: true,
                        config: {
                            quotePlaceholder: 'Scrivi una citazione...',
                            captionPlaceholder: 'Autore',
                        },
                    },
                    list: {
                        class: NestedList,
                        inlineToolbar: true,
                        config: {
                            defaultStyle: 'unordered'
                        },
                    },
                    delimiter: Delimiter,
                    table: {
                        class: Table,
                        inlineToolbar: ['link', 'italic', 'bold'],
                    },
                    // TODO: #12 Rimuovi i bottoni (withBorder, stretched, withBackground) quando si carica un'immagine
                    image: {
                        class: ImageTool,
                        config: {
                            captionPlaceholder: 'Caption...',
                            endpoints: {
                                byFile: pathSite+'/api/task/blog/image.php'
                            },
                            type: [ 'image/png', 'image/jpg', 'image/jpeg']
                        }
                    },
                    // TODO: #16 Rimuovi i bottoni (fit e slider)
                    gallery: {
                        class: ImageGallery,
                        config: {
                            captionPlaceholder: 'Caption galleria...',
                            endpoints: {
                                byFile: pathSite+'/api/task/blog/image.php'
                            },
                            type: [ 'image/png', 'image/jpg', 'image/jpeg']
                        },
                    },
                    textAlign: TextAlign,
                    Marker: Marker,
                    inlineCode: InlineCode,
                    code: CodeTool,
                    // TODO: #13 embed non funziona. Trovare un modo per caricare iframe
                    embed: Embed
                };
                
            }

            new EditorJS({
                tools: tools,
                holder: editorId, 
                autofocus: true,
                data: JSON.parse(data),
                onChange: (api) => {

                    api.saver.save().then((outputData) => {
                        document.getElementById(textareaId).value = JSON.stringify((outputData.blocks));
                    }).catch((error) => {
                        console.log('Saving failed: ', error)
                    });

                },
                i18n: {
                    messages: {
                        ui: {
                            "blockTunes": {
                                "toggler": {
                                    "Click to tune": "Modifica",
                                    "or drag to move": "Trascina per muovere"
                                },
                            },
                            "inlineToolbar": {
                                "converter": {
                                    "Convert to": "Converti"
                                }
                            },
                            "toolbar": {
                                "toolbox": {
                                    "Add": "Aggiungi",
                                }
                            }
                        },
                        toolNames: {
                            "Add": "Aggiungi",
                            "Quote": "Citazione",
                            "Delimiter": "Linea",
                            "Text": "Testo",
                            "Heading": "Titolo",
                            "Table": "Tabella",
                            "Code": "Codice",
                            "List": "Elenco",
                            "Bold": "Grassetto",
                            "Italic": "Corsivo",
                            "InlineCode": "Codice",
                            "TextAlign": "Allineamento",
                            "Gallery": "Galleria",
                            "Image": "Immagine",
                        },
                        tools: {
                            "header": {
                                "Heading 1": "Titolo grande",
                                "Heading 2": "Titolo",
                                "Heading 4": "Sottotitolo"
                            },
                            "link": {
                                "Add a link": "Aggiungi link"
                            },
                            "code": {
                                "Enter a code": "Inserisci codice",
                            },
                            "quote": {
                                "Align Left": "Allinea a sinistra",
                                "Align Center": "Allinea in centro",
                            },
                            "table": {
                                "With headings": "Con intestazione",
                                "Without headings": "Senza intestazione",
                                "Add column to left": "Aggiungi colonna sinistra",
                                "Add column to right": "Aggiungi colonna destra",
                                "Add row above": "Aggiungi riga sopra",
                                "Add row below": "Aggiungi riga sotto",
                                "Delete column": "Elimina colonna",
                                "Delete row": "Elimina riga",
                            },
                            "list": {
                                "Ordered": "Elenco numerato",
                                "Unordered": "Elenco puntato",
                            },
                            "gallery": {
                                "Select an Image": "Seleziona immagini",
                            },
                            "image": {
                                "Select an Image": "Seleziona immagine",
                            },
                        },
                        blockTunes: {
                            "delete": {
                                "Delete": "Elimina",
                                "Click to delete": "Clicca per elimina"
                            },
                            "moveUp": {
                                "Move up": "Su"
                            },
                            "moveDown": {
                                "Move down": "Giù"
                            }
                        },
                    }
                }
            });

        }

    });

}