(function ($) {
    function Metabox($container) {
        this.$container = $container;
        this.$promptBtn = $container.find('.gig-generate-prompt');
        this.$promptField = $container.find('.gig-prompt-field');
        this.$styleSelect = $container.find('.gig-select-style');
        this.$moodSelect = $container.find('.gig-select-mood');
        this.$colorsSelect = $container.find('.gig-select-colors');
        this.$ratioSelect = $container.find('.gig-select-ratio');
        this.$qualitySelect = $container.find('.gig-select-quality');
        this.$formatSelect = $container.find('.gig-select-format');
        this.$status = $container.find('.gig-status');
        this.$imageBtn = $container.find('.gig-generate-image');
        this.$preview = $container.find('.gig-preview');
        this.$previewImg = $container.find('.gig-preview-image');
        this.$generateNew = $container.find('.gig-generate-new');
        this.$setFeatured = $container.find('.gig-set-featured');
        this.$sectionsWrap = $container.find('.gig-sections');
        this.$sectionsList = $container.find('.gig-sections-list');
        this.$sectionsRefresh = $container.find('.gig-refresh-sections');
        this.currentAttachment = null;
        this.postId = $container.data('post');
        this.sections = [];

        this.bindEvents();
        this.applyDefaults();
        this.fetchSections();
    }

    Metabox.prototype.bindEvents = function () {
        var self = this;

        this.$promptBtn.on('click', function () {
            self.generatePrompt();
        });

        this.$imageBtn.on('click', function () {
            self.generateImage();
        });

        this.$generateNew.on('click', function () {
            self.generateImage();
        });

        this.$setFeatured.on('click', function () {
            self.setFeaturedImage();
        });

        this.$sectionsRefresh.on('click', function (e) {
            e.preventDefault();
            self.fetchSections(true);
        });

        this.$previewImg.on('click', function () {
            var src = $(this).attr('src');
            if (src) {
                self.openImageLightbox(src);
            }
        });

        this.$sectionsList.on('click', '.gig-section-preview img', function () {
            var src = $(this).attr('src');
            if (src) {
                self.openImageLightbox(src);
            }
        });
    };

    Metabox.prototype.applyDefaults = function () {
        if (!GIGMetabox || !GIGMetabox.defaults) return;

        var d = GIGMetabox.defaults;
        if (d.ratio) this.$ratioSelect.val(d.ratio);
        if (d.quality) this.$qualitySelect.val(d.quality);
        if (d.format) this.$formatSelect.val(d.format);
        if (d.style) this.$styleSelect.val(d.style);
        if (d.mood) this.$moodSelect.val(d.mood);
        if (d.colors) this.$colorsSelect.val(d.colors);
    };

    Metabox.prototype.setStatus = function (message, type) {
        this.$status
            .removeClass('success error loading')
            .addClass(type || '')
            .text(message || '');
    };

    Metabox.prototype.setLoading = function (loading) {
        this.$container.toggleClass('loading', loading);
        this.$promptBtn.prop('disabled', loading);
        this.$imageBtn.prop('disabled', loading);
        this.$generateNew.prop('disabled', loading);
    };

    Metabox.prototype.generatePrompt = function () {
        var self = this;
        this.setLoading(true);
        this.setStatus(GIGMetabox.strings.generatingPrompt, 'loading');

        $.post(GIGMetabox.ajaxUrl, {
            action: 'gig_generate_prompt',
            nonce: GIGMetabox.nonce,
            post_id: self.postId
        }).done(function (response) {
            if (response.success) {
                self.$promptField.val(response.data.prompt.trim());
                self.setStatus('');
            } else {
                self.setStatus(response.data || 'Fehler', 'error');
            }
        }).fail(function () {
            self.setStatus('Verbindungsfehler', 'error');
        }).always(function () {
            self.setLoading(false);
        });
    };

    Metabox.prototype.generateImage = function (options) {
        var self = this;
        options = options || {};

        var prompt = options.prompt || this.$promptField.val().trim();

        if (!prompt) {
            this.setStatus(GIGMetabox.strings.missingPrompt, 'error');
            return;
        }

        this.setLoading(true);
        this.setStatus(GIGMetabox.strings.generatingImage, 'loading');

        var payload = {
            action: 'gig_generate_image',
            nonce: GIGMetabox.nonce,
            post_id: self.postId,
            prompt: prompt,
            style: options.style || self.$styleSelect.val(),
            mood: options.mood || self.$moodSelect.val(),
            colors: options.colors || self.$colorsSelect.val(),
            ratio: options.ratio || self.$ratioSelect.val(),
            quality: options.quality || self.$qualitySelect.val(),
            format: options.format || self.$formatSelect.val(),
            max_width: options.max_width || '',
            max_height: options.max_height || ''
        };

        if (options.section_id) {
            payload.section_id = options.section_id;
        }

        $.post(GIGMetabox.ajaxUrl, payload)
            .done(function (response) {
                if (response.success) {
                    self.currentAttachment = response.data.attachment_id;

                    self.$previewImg.attr('src', response.data.image_url || '');
                    self.$preview.show();
                    self.setStatus('');

                    if (options.onSuccess) {
                        options.onSuccess(response.data);
                    }
                } else {
                    self.setStatus(response.data || 'Generierung fehlgeschlagen', 'error');
                    if (options.onError) {
                        options.onError(response);
                    }
                }
            })
            .fail(function () {
                self.setStatus('Verbindung zur API fehlgeschlagen', 'error');
            })
            .always(function () {
                self.setLoading(false);
            });
    };

    Metabox.prototype.setFeaturedImage = function () {
        var self = this;
        if (!self.currentAttachment) {
            this.setStatus('Kein Bild vorhanden', 'error');
            return;
        }

        self.setStatus('Wird gesetzt…', 'loading');
        self.$setFeatured.prop('disabled', true);

        $.post(GIGMetabox.ajaxUrl, {
            action: 'gig_set_featured_image',
            nonce: GIGMetabox.nonce,
            post_id: self.postId,
            attachment_id: self.currentAttachment
        }).done(function (response) {
            if (response.success) {
                self.setStatus(GIGMetabox.strings.setFeatured, 'success');
                if (typeof wp !== 'undefined' && wp.media && wp.media.featuredImage) {
                    wp.media.featuredImage.set(self.currentAttachment);
                }
            } else {
                self.setStatus(response.data || 'Fehler', 'error');
            }
        }).fail(function () {
            self.setStatus('Serverfehler', 'error');
        }).always(function () {
            self.$setFeatured.prop('disabled', false);
        });
    };

    /**
     * Abschnitts-Funktionen
     */
    Metabox.prototype.fetchSections = function (force) {
        var self = this;
        if (self.$sectionsWrap.data('loaded') === true && !force) {
            return;
        }

        self.$sectionsList.html('<p class="gig-sections-placeholder">' + GIGMetabox.strings.loadingSections + '</p>');

        $.post(GIGMetabox.ajaxUrl, {
            action: 'gig_get_sections',
            nonce: GIGMetabox.nonce,
            post_id: self.postId
        }).done(function (response) {
            if (response.success) {
                self.sections = response.data.sections || [];
                self.renderSections();
                self.$sectionsWrap.data('loaded', true);
            } else {
                self.$sectionsList.html('<p class="gig-sections-placeholder">' + (response.data || 'Fehler beim Laden der Abschnitte') + '</p>');
            }
        }).fail(function () {
            self.$sectionsList.html('<p class="gig-sections-placeholder">Serverfehler</p>');
        });
    };

    Metabox.prototype.renderSections = function () {
        var self = this;

        if (!self.sections.length) {
            self.$sectionsList.html('<p class="gig-sections-placeholder">' + GIGMetabox.strings.noSections + '</p>');
            return;
        }

        var html = '';
        self.sections.forEach(function (section) {
            html += '<div class="gig-section-card" data-section="' + section.id + '">';
            html += '<button type="button" class="gig-section-card-toggle">';
            html += '<span class="gig-section-card-title">' + $('<div>').text(section.title).html() + '</span>';
            html += '<span class="gig-section-card-icon">+</span>';
            html += '</button>';
            html += '<div class="gig-section-card-body" style="display:none;">';
            html += '<p class="gig-section-card-excerpt">' + $('<div>').text(section.excerpt).html() + '</p>';
            html += '<div class="gig-section-card-actions">';
            html += '<button type="button" class="button gig-section-prompt">Prompt</button>';
            html += '<button type="button" class="button gig-section-generate">Bild</button>';
            html += '<button type="button" class="button gig-section-insert" disabled>Einfügen</button>';
            html += '</div>';
            html += '<textarea class="gig-section-prompt-field" rows="3" placeholder="Prompt für diesen Abschnitt"></textarea>';
            html += '<div class="gig-section-advanced">';
            html += '<label>Format<select class="gig-section-ratio">';
            ['16:9','4:3','1:1','9:16','3:4'].forEach(function(ratio){
                html += '<option value="' + ratio + '">' + ratio + '</option>';
            });
            html += '</select></label>';
            html += '<label>Max. Breite <input type="number" class="gig-section-max-width" min="100" max="4000" step="10" placeholder="z.B. 1200"></label>';
            html += '<label>Max. Höhe <input type="number" class="gig-section-max-height" min="100" max="4000" step="10" placeholder="z.B. 800"></label>';
            html += '</div>';
            html += '<div class="gig-section-preview" style="display:none;">';
            html += '<img src="" alt="" />';
            html += '</div>';
            html += '</div>';
            html += '</div>';
        });

        self.$sectionsList.html(html);
        self.bindSectionEvents();
    };

    Metabox.prototype.bindSectionEvents = function () {
        var self = this;

        self.$sectionsList.find('.gig-section-prompt').off('click').on('click', function () {
            var $card = $(this).closest('.gig-section-card');
            self.generateSectionPrompt($card);
        });

        self.$sectionsList.find('.gig-section-generate').off('click').on('click', function () {
            var $card = $(this).closest('.gig-section-card');
            self.generateSectionImage($card);
        });

        self.$sectionsList.find('.gig-section-insert').off('click').on('click', function () {
            var $card = $(this).closest('.gig-section-card');
            self.insertSectionImage($card);
        });

        self.$sectionsList.find('.gig-section-card-toggle').off('click').on('click', function () {
            var $card = $(this).closest('.gig-section-card');
            var $icon = $(this).find('.gig-section-card-icon');
            $card.toggleClass('open');
            $card.find('.gig-section-card-body').slideToggle(150);
            $icon.text($card.hasClass('open') ? '−' : '+');
        });
    };

    Metabox.prototype.generateSectionPrompt = function ($card) {
        var self = this;
        var sectionId = $card.data('section');
        var $promptField = $card.find('.gig-section-prompt-field');

        $card.addClass('loading');
        $promptField.val('…');

        $.post(GIGMetabox.ajaxUrl, {
            action: 'gig_generate_section_prompt',
            nonce: GIGMetabox.nonce,
            post_id: self.postId,
            section_id: sectionId
        }).done(function (response) {
            if (response.success) {
                $promptField.val(response.data.prompt.trim());
            } else {
                $promptField.val('');
                alert(response.data || 'Fehler beim Generieren des Prompts');
            }
        }).fail(function () {
            $promptField.val('');
            alert('Serverfehler beim Generieren des Prompts');
        }).always(function () {
            $card.removeClass('loading');
        });
    };

    Metabox.prototype.generateSectionImage = function ($card) {
        var self = this;
        var prompt = $card.find('.gig-section-prompt-field').val().trim();

        if (!prompt) {
            alert('Bitte zuerst einen Prompt generieren oder eingeben.');
            return;
        }

        var sectionId = $card.data('section');
        var $preview = $card.find('.gig-section-preview');
        var $previewImg = $preview.find('img');
        var $insertBtn = $card.find('.gig-section-insert');

        var options = {
            prompt: prompt,
            section_id: sectionId,
            ratio: $card.find('.gig-section-ratio').val(),
            max_width: $card.find('.gig-section-max-width').val(),
            max_height: $card.find('.gig-section-max-height').val(),
            onSuccess: function (data) {
                $previewImg.attr('src', data.image_url || '');
                $preview.show();
                $insertBtn.data('image', {
                    url: data.image_url,
                    alt: data.alt || '',
                    caption: data.caption || '',
                    attachment: data.attachment_id,
                    section_id: sectionId
                });
                $insertBtn.prop('disabled', false);
            },
            onError: function () {
                $insertBtn.prop('disabled', true);
            }
        };

        $card.addClass('loading');
        $insertBtn.prop('disabled', true);

        this.generateImage(options);

        $card.removeClass('loading');
    };

    Metabox.prototype.insertSectionImage = function ($card) {
        var data = $card.find('.gig-section-insert').data('image');
        if (!data) {
            alert('Kein Bild zum Einfügen gefunden.');
            return;
        }

        var section = this.sections.find(function (sec) {
            return sec.id === data.section_id;
        });

        if (!section) {
            alert('Abschnitt nicht mehr verfügbar.');
            return;
        }

        var html = '\n<figure class="gig-section-image">\n';
        html += '<img src="' + data.url + '" alt="' + this.escapeHtml(data.alt || '') + '">\n';
        if (data.caption) {
            html += '<figcaption>' + this.escapeHtml(data.caption) + '</figcaption>\n';
        }
        html += '</figure>\n';

        this.insertHtmlAfterHeading(section.title, html);
    };

    Metabox.prototype.insertHtmlAfterHeading = function (headingText, html) {
        var content = this.getEditorContent();
        if (!content) {
            alert('Editor-Inhalt konnte nicht gelesen werden.');
            return;
        }

        var pattern = new RegExp('(<h2[^>]*>\\s*' + this.escapeRegex(headingText) + '\\s*</h2>)', 'i');
        if (!pattern.test(content)) {
            alert('Überschrift wurde im Inhalt nicht gefunden.');
            return;
        }

        var updated = content.replace(pattern, '$1\n' + html);
        this.setEditorContent(updated);
    };

    Metabox.prototype.getEditorContent = function () {
        if (typeof tinymce !== 'undefined') {
            var editor = tinymce.get('content');
            if (editor && !editor.isHidden()) {
                return editor.getContent({ format: 'html' });
            }
        }
        return $('#content').val();
    };

    Metabox.prototype.setEditorContent = function (content) {
        if (typeof tinymce !== 'undefined') {
            var editor = tinymce.get('content');
            if (editor && !editor.isHidden()) {
                editor.setContent(content);
            }
        }
        $('#content').val(content);
    };

    Metabox.prototype.escapeRegex = function (text) {
        return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    };

    Metabox.prototype.escapeHtml = function (text) {
        return text.replace(/[&<>"']/g, function (char) {
            var entities = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return entities[char] || char;
        });
    };

    Metabox.prototype.openImageLightbox = function (url) {
        window.open(url, '_blank', 'noopener');
    };

    $(function () {
        $('.gig-metabox').each(function () {
            new Metabox($(this));
        });
    });
})(jQuery);
