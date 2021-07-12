define(['jquery', 'underscore', 'mage/template'], function ($, _, mageTemplate) {
        'use strict';
        return {
            build: function (formData) {
                var formTmpl = mageTemplate(
                    '<form action="<%= data.action %>" id="fintecture_payment_form"' +
                    ' method="POST" hidden enctype="application/x-www-form-urlencoded"></form>'
                );
                return $(
                    formTmpl(
                        {
                            data: {
                                action: formData.action
                            }
                        }
                    )
                ).appendTo($('[data-container="body"]'));
            }
        };
    }
);
