class CustomHyperlink extends Hyperlink {
    iconSvg(name, width = 14, height = 14) {
      const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
      svg.classList.add('icon', 'icon--' + name);
      svg.setAttribute('width', '24');
      svg.setAttribute('height','24');
      svg.setAttribute('viewBox', '0 0 24 24')
      svg.setAttribute('fill', 'none');
      if (name == 'link') {
        svg.innerHTML = `
          <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M7.69998 12.6L7.67896 12.62C6.53993 13.7048 6.52012 15.5155 7.63516 16.625V16.625C8.72293 17.7073 10.4799 17.7102 11.5712 16.6314L13.0263 15.193C14.0703 14.1609 14.2141 12.525 13.3662 11.3266L13.22 11.12"></path><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M16.22 11.12L16.3564 10.9805C17.2895 10.0265 17.3478 8.5207 16.4914 7.49733V7.49733C15.5691 6.39509 13.9269 6.25143 12.8271 7.17675L11.3901 8.38588C10.0935 9.47674 9.95706 11.4241 11.0888 12.6852L11.12 12.72"></path>
        `;
      } else {
        svg.innerHTML = `
          <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M15.7795 11.5C15.7795 11.5 16.053 11.1962 16.5497 10.6722C17.4442 9.72856 17.4701 8.2475 16.5781 7.30145V7.30145C15.6482 6.31522 14.0873 6.29227 13.1288 7.25073L11.8796 8.49999"></path><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M8.24517 12.3883C8.24517 12.3883 7.97171 12.6922 7.47504 13.2161C6.58051 14.1598 6.55467 15.6408 7.44666 16.5869V16.5869C8.37653 17.5731 9.93744 17.5961 10.8959 16.6376L12.1452 15.3883"></path><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M17.7802 15.1032L16.597 14.9422C16.0109 14.8624 15.4841 15.3059 15.4627 15.8969L15.4199 17.0818"></path><path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M6.39064 9.03238L7.58432 9.06668C8.17551 9.08366 8.6522 8.58665 8.61056 7.99669L8.5271 6.81397"></path><line x1="12.1142" x2="11.7" y1="12.2" y2="11.7858" stroke="currentColor" stroke-linecap="round" stroke-width="2"></line>
        `;
      }
      return svg;
    }
}

class CustomEmded extends Embed {
    
    static get toolbox() {
      return {
        title: 'Iframe',
        icon: '<svg width="20" height="20" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 1 0-6M14 11a5 5 0 0 1 0 6"></path><line x1="14" y1="7" x2="14" y2="7"></line><line x1="10" y1="17" x2="10" y2="17"></line></svg>'
      };
    }
  
    /**
     * Render Embed tool content
     *
     * @returns {HTMLElement}
     */
    render() {
      if (!this.data.service) {
        const container = document.createElement('div');
  
        this.element = container;
        const input = document.createElement('input');
        input.classList.add('cdx-input');
        input.placeholder = 'https://www.youtube.com/watch?v=w8vsuOXZBXc';
        input.type = 'url';
        input.addEventListener('paste', (event) => {
          const url = event.clipboardData.getData('text');
          const service = Object.keys(Embed.services).find((key) => Embed.services[key].regex.test(url));
          if (service) {
            this.onPaste({detail: {key: service, data: url}});
          }
        });
        container.appendChild(input);
  
        return container;
      }
      return super.render();
    }
    
    validate(savedData) {
      return savedData.service && savedData.source ? true : false;
    }
}

class CustomVideoTool extends VideoTool {
    
    static get toolbox() {
        return {
            title: 'Video',
            icon: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10 10.5606V13.4394C10 14.4777 11.1572 15.0971 12.0211 14.5211L14.1803 13.0817C14.9536 12.5661 14.9503 11.4317 14.18 10.9181L12.0214 9.47907C11.1591 8.9042 10 9.5203 10 10.5606Z" stroke="black" stroke-width="2"/><rect x="5" y="5" width="14" height="14" rx="4" stroke="black" stroke-width="2"/></svg>'
        };
    }
    
}

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
    // code: CodeTool,
    image: {
        class: ImageTool,
        config: {
            captionPlaceholder: 'Caption...',
            endpoints: {
                byFile: pathApp+'/api/backend/editorjs/image.php'
                // Aggiungere cartella dove caricare i file
            },
            field: 'image',
            types: 'image/png, image/jpg, image/jpeg',
            additionalRequestHeaders: { dir: '' }
            // Forzo nel css la rimozione "display: none" di withBorder, stretched e withBackground
        }
    },
    gallery: {
        class: ImageGallery,
        config: {
            captionPlaceholder: 'Caption galleria...',
            endpoints: {
                byFile: pathApp+'/api/backend/editorjs/image.php'
                // Aggiungere cartella dove caricare i file
            },
            field: 'image',
            types: 'image/png, image/jpg, image/jpeg',
            additionalRequestHeaders: { dir: '' }
            // Forzo nel css la rimozione "display: none" di fit e slider
        },
    },
    Marker: Marker,
    inlineCode: InlineCode,
    attaches: {
        class: AttachesTool,
        config: {
            endpoint: pathApp+'/api/backend/editorjs/file.php',
            // Aggiungere cartella dove caricare i file
            field: 'file',
            additionalRequestHeaders: { dir: '' }
        }
    },
    // TODO: Aggiungi caricamento video (potrebbe tornare utile la classe ImageTool)
    // video: {
    //     class: CustomVideoTool,
    //     config: {
    //         captionPlaceholder: 'Caption...',
    //         endpoints: {
    //             byFile: pathSite+'/api/task/article/file.php'
    //         },
    //         player: {
    //             controls: true,
    //             autoplay: false,
    //         },
    //         field: 'file',
    //         types: 'video/quicktime, video/mp4'
    //         // Forzo nel css la rimozione "display: none" di withBorder, stretched e withBackground
    //     }
    // },
    // TODO: embed non funziona. Trovare un modo per caricare iframe
    // embed: {
    //     class: CustomEmded,
    // },
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
        inlineToolbar: ['link', 'italic', 'bold'],
    }
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
            "Attachment": "Allega file",
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
            "video": {
                "Select an Video": "Seleziona video",
            },
            "attaches": {
                "Select file to upload": "Seleziona file da allegare",
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