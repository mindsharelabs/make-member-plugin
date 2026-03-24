(function ($, acf) {
    'use strict';

    var config = window.makeGravityFormEntriesAdmin || null;

    if (!config || typeof acf === 'undefined') {
        return;
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function getField($container, fieldKey) {
        return $container.find('.acf-field[data-key="' + fieldKey + '"]').first();
    }

    function getSelectedValues($field) {
        var selected = [];

        $field.find('input[type="checkbox"]:checked').each(function () {
            selected.push($(this).val());
        });

        return selected;
    }

    function getHiddenInputName($field) {
        return $field.find('.acf-input input[type="hidden"]').first().attr('name') || '';
    }

    function renderMessage($field, message) {
        var hiddenName = getHiddenInputName($field);
        var html = '';

        if (hiddenName) {
            html += '<input type="hidden" name="' + escapeHtml(hiddenName) + '" value="">';
        }

        html += '<p class="description">' + escapeHtml(message) + '</p>';

        $field.find('> .acf-input').html(html);
    }

    function renderChoices($field, choices, selectedValues) {
        var hiddenName = getHiddenInputName($field);
        var inputName = hiddenName ? hiddenName + '[]' : '';
        var html = '';

        if (hiddenName) {
            html += '<input type="hidden" name="' + escapeHtml(hiddenName) + '" value="">';
        }

        html += '<ul class="acf-checkbox-list acf-bl" role="group">';

        $.each(choices, function (index, choice) {
            var value = choice && typeof choice.value !== 'undefined' ? String(choice.value) : '';
            var label = choice && typeof choice.label !== 'undefined' ? String(choice.label) : '';
            var checked = selectedValues.indexOf(value) !== -1 ? ' checked="checked"' : '';

            if (!value || !label) {
                return;
            }

            html += '<li>';
            html += '<label>';
            html += '<input type="checkbox" name="' + escapeHtml(inputName) + '" value="' + escapeHtml(value) + '"' + checked + '>';
            html += ' ' + escapeHtml(label);
            html += '</label>';
            html += '</li>';
        });

        html += '</ul>';

        $field.find('> .acf-input').html(html);
    }

    function requestChoices($group) {
        var $formField = getField($group, config.formFieldKey);
        var $listField = getField($group, config.listFieldKey);

        if (!$formField.length || !$listField.length) {
            return;
        }

        var formId = $formField.find('select').val();
        var selectedValues = getSelectedValues($listField);

        if (!formId) {
            renderMessage($listField, config.emptyMessage);
            return;
        }

        $.post(config.ajaxUrl, {
            action: config.action,
            nonce: config.nonce,
            form_id: formId
        }).done(function (response) {
            var choices = response && response.success && response.data && $.isArray(response.data.choices) ? response.data.choices : [];
            var validValues = {};
            var validSelected = [];

            $.each(choices, function (index, choice) {
                if (choice && typeof choice.value !== 'undefined') {
                    validValues[String(choice.value)] = true;
                }
            });

            $.each(selectedValues, function (index, value) {
                if (validValues[String(value)]) {
                    validSelected.push(String(value));
                }
            });

            if (!choices.length) {
                renderMessage($listField, config.noneMessage);
                return;
            }

            renderChoices($listField, choices, validSelected);
        }).fail(function () {
            renderMessage($listField, config.noneMessage);
        });
    }

    function bindGroup($formField) {
        if ($formField.data('makeGfEntriesBound')) {
            return;
        }

        $formField.data('makeGfEntriesBound', true);

        $formField.on('change', 'select', function () {
            var $group = $formField.closest('.acf-field[data-key="' + config.groupFieldKey + '"]');

            if ($group.length) {
                requestChoices($group);
            }
        });
    }

    function init(context) {
        $(context).find('.acf-field[data-key="' + config.formFieldKey + '"]').each(function () {
            var $formField = $(this);
            var $group = $formField.closest('.acf-field[data-key="' + config.groupFieldKey + '"]');

            if (!$group.length) {
                return;
            }

            bindGroup($formField);
            requestChoices($group);
        });
    }

    acf.addAction('ready', init);
    acf.addAction('append', init);
})(jQuery, window.acf);
