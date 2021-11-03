requirejs(['jquery', 'mage/translate'], function ($, $t) {
        'use strict';
        $(document).ready(function () {
            const loader = $('#loader');
            const failMessage = '<span class="error">' + $t('Connection did not succeed. Make sure you have entered the right parameters.') + '</span>';
            const successMessage = '<span class="success">' + $t('Connection succeeded') + '</span>';
            const fields = 'groups[fintecture][groups][general][fields]';
            const fieldsSpecifications = 'groups[fintecture][groups][payment_options][fields]';
            const fintectureEnv = $('select[name="' + fields + '[environment][value]"]').val() ?? '';

            $('input[name="' + fields + '[title][value]"]').attr('disabled', 'disabled');
            $('input[name="' + fields + '[title][value]"]').val($t('Instant bank payment'));

            $(document).on('click', '#test-connection', function (e) {
                e.preventDefault();
                showMessage('');
                const fintectureAppId = $('input[name="' + fields + '[fintecture_app_id_' + fintectureEnv + '][value]"]').val() ?? '';
                const fintectureAppSecret = $('input[name="' + fields + '[fintecture_app_secret_' + fintectureEnv + '][value]"]').val() ?? '';
                const fintecturePrivateKey = $('input[name="' + fields + '[custom_file_upload_' + fintectureEnv + '][value]')?.get(0)?.files[0] ?? '';

                if (fintectureAppId === '' || fintectureAppSecret === '') {
                    showMessage(failMessage);
                    return;
                }

                if (!fintecturePrivateKey) {
                    sendAjax(fintectureEnv, fintectureAppId, fintectureAppSecret, '');
                } else {
                    let reader = new FileReader();
                    reader.addEventListener('load', function (e) {
                        sendAjax(fintectureEnv, fintectureAppId, fintectureAppSecret, e.target.result);
                    });
                    reader.readAsText(fintecturePrivateKey);
                }
            });

            function showMessage(message) {
                loader.hide();
                $('#connect-message').html(message);
            }

            function sendAjax(fintectureEnv, fintectureAppId, fintectureAppSecret, fintecturePrivateKey) {
                $.ajax({
                    showLoader: true,
                    url: configurationAjaxUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        fintectureEnv: fintectureEnv,
                        fintectureAppId: fintectureAppId,
                        fintectureAppSecret: fintectureAppSecret,
                        fintecturePrivateKey: fintecturePrivateKey,
                    },
                    beforeSend: function () {
                        loader.show();
                    },
                    success: function (response, textStatus, jqXHR) {
                        if (response instanceof Object) {
                            showMessage(failMessage);
                            return;
                        }
                        const message = response ? successMessage : failMessage;
                        showMessage(message);
                    },
                    error: function () {
                        showMessage(failMessage);
                    }
                })
            }

            $('select[name="' + fields + '[environment][value]"]').change(function () {
                showMessage('');
            });

            $('select[name="' + fieldsSpecifications + '[specificcountry][value][]"]').change(function () {
                let isFranceSelected = false;
                if ($(this).find("option:selected").val() === 'FR') {
                    isFranceSelected = true;
                }
                var applicableCountry = $('select[name="' + fieldsSpecifications + '[allowspecific][value]"]').val();
                if (applicableCountry === '1' && !isFranceSelected) {
                    alert($t('Only French banks are supported at this time.'));
                }
            });

            $('select[name="' + fieldsSpecifications + '[allowspecific][value]"]').change(function () {

                let isFranceSelected = false;
                if ($('select[name="' + fieldsSpecifications + '[specificcountry][value][]"]').find("option:selected").val() === 'FR') {
                    isFranceSelected = true;
                }
                if (this.value === '1' && !isFranceSelected) {
                    alert($t('Only French banks are supported at this time.'));
                }
            });

            window.ckoToggleSolution = function (id, url) {
                let doScroll = false;
                Fieldset.toggleCollapse(id, url);

                if (this.classList.contains('open')) {
                    $('.with-button button.button').each(function (index, otherButton) {
                        if (otherButton !== this && otherButton.classList.contains('open')) {
                            $(otherButton).click();
                            doScroll = true;
                        }
                    }
                        .bind(this));
                }

                if (doScroll) {
                    const pos = Element.cumulativeOffset($(this));
                    window.scrollTo(pos[0], pos[1] - 45);
                }
            }
        });
    }
);
