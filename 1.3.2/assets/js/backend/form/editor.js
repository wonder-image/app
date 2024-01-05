var EDITORJS_TOOLS_BLOG = {
    paragraph: {
        inlineToolbar: true,
        tunes: ['textAlign']
    },
    header: {
        class: Header,
        inlineToolbar: true,
        tunes: ['textAlign'],
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
    image: {
        class: ImageTool,
        config: {
            captionPlaceholder: 'Caption...',
            endpoints: {
                byFile: pathSite+'/api/task/blog/image.php'
            },
            type: [ 'image/png', 'image/jpg', 'image/jpeg' ]
            // Forzo nel css la rimozione di withBorder, stretched e withBackground
        }
    },
    gallery: {
        class: ImageGallery,
        config: {
            captionPlaceholder: 'Caption galleria...',
            endpoints: {
                byFile: pathSite+'/api/task/blog/image.php'
            },
            type: [ 'image/png', 'image/jpg', 'image/jpeg' ],
            // Forzo nel css la rimozione di fit e slider
        },
    },
    Marker: Marker,
    inlineCode: InlineCode,
    code: CodeTool,
    // TODO: Aggiungi caricamento file
    // TODO: Aggiungi caricamento video (potrebbe tornare utile la classe ImageTool)
    // TODO: embed non funziona. Trovare un modo per caricare iframe
    // embed: Embed,
    textAlign: {
        class: AlignmentBlockTune,
        config:{
            default: "left"
        },
    },
    link: {
        class: CustomHyperlink,
        config: {
          shortcut: 'CMD+L',
          target: '_blank',
          rel: 'nofollow',
          availableTargets: ['_blank', '_self'],
          availableRels: ['author', 'noreferrer'],
          validate: false,
        }
      },
};

var EDITORJS_TOOLS_TABLE = {
    paragraph: false,
    inlineCode: InlineCode,
    table: {
        class: Table,
        inlineToolbar: [ 'bold', 'link' ]
    },
    textAlign: TextAlign
};

var EDITORJS_i18n_IT = {
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
            "Marker": "Evidenzia",
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
};