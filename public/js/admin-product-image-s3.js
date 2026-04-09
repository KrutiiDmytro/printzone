/**
 * Presigned PUT у S3 для поля Product[image] (EasyAdmin).
 */
(function () {
    function log(msg) {
        if (window.console && window.console.log) {
            console.log('[S3-upload] ' + msg);
        }
    }

    function initRow(row) {
        var input = row.querySelector('input.ea-product-image-s3-key');
        if (!input || input.dataset.s3Bound === '1') {
            return;
        }
        input.dataset.s3Bound = '1';

        var presignUrl = input.getAttribute('data-presign-url');
        var csrf = input.getAttribute('data-csrf-token');
        if (!presignUrl || !csrf) {
            log('Поле знайдено, але data-presign-url або data-csrf-token відсутні!');
            return;
        }
        log('Ініціалізовано поле. presign=' + presignUrl);

        var container = document.createElement('div');
        container.className = 'ea-s3-upload-container mt-2';
        container.style.cssText = 'display:block!important;visibility:visible!important';

        var label = document.createElement('label');
        label.className = 'd-block fw-semibold mb-1';
        label.style.cssText = 'display:block!important;font-size:.875rem';
        label.textContent = 'Завантажити зображення в S3:';

        var fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = 'image/jpeg,image/png,image/gif,image/webp';
        fileInput.className = 'form-control';
        fileInput.style.cssText = 'display:block!important;max-width:400px';

        var status = document.createElement('div');
        status.className = 'mt-1';
        status.style.cssText = 'font-size:.85rem;display:block!important';

        var preview = document.createElement('img');
        preview.className = 'img-thumbnail mt-2';
        preview.style.cssText = 'max-height:100px;display:none';
        preview.alt = '';

        container.appendChild(label);
        container.appendChild(fileInput);
        container.appendChild(status);
        container.appendChild(preview);

        var currentKeyDiv = null;
        if (input.value) {
            currentKeyDiv = document.createElement('div');
            currentKeyDiv.className = 'text-muted mt-1';
            currentKeyDiv.style.fontSize = '.8rem';
            currentKeyDiv.textContent = 'Поточний ключ: ' + input.value;
            container.appendChild(currentKeyDiv);
        }

        var widget = row.querySelector('.form-widget');
        var inserted = false;

        if (widget) {
            var afterInput = input.nextSibling;
            if (afterInput) {
                widget.insertBefore(container, afterInput);
            } else {
                widget.appendChild(container);
            }
            inserted = true;
        }

        if (!inserted) {
            row.appendChild(container);
        }

        log('UI вставлено. Чекаємо вибір файлу...');

        fileInput.addEventListener('change', function () {
            var file = fileInput.files && fileInput.files[0];
            if (!file) {
                return;
            }
            log('Файл вибрано: ' + file.name + ' (' + file.type + ', ' + file.size + ' bytes)');

            status.innerHTML = '<span class="text-info">⏳ Отримую presigned URL...</span>';

            var mime = file.type;
            if (!mime) {
                var n = file.name.toLowerCase();
                if (n.endsWith('.jpg') || n.endsWith('.jpeg')) {
                    mime = 'image/jpeg';
                } else if (n.endsWith('.png')) {
                    mime = 'image/png';
                } else if (n.endsWith('.gif')) {
                    mime = 'image/gif';
                } else if (n.endsWith('.webp')) {
                    mime = 'image/webp';
                }
            }
            if (!mime) {
                status.textContent = 'Не удалось определить тип файла (укажите .jpg, .png, .gif или .webp).';
                status.classList.add('text-danger');
                return;
            }

            var body = JSON.stringify({
                filename: file.name,
                contentType: mime,
            });

            // Крок 1: presign (same-origin, CORS не потрібен)
            fetch(presignUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: body,
            })
                .then(function (r) {
                    return r.text().then(function (text) {
                        var data = null;
                        try {
                            data = text ? JSON.parse(text) : null;
                        } catch (e) {
                            throw new Error(
                                'Presign: не JSON (HTTP ' + r.status + '). Відповідь: ' + (text ? text.slice(0, 300) : '(порожньо)')
                            );
                        }
                        return { ok: r.ok, status: r.status, data: data };
                    });
                })
                .catch(function (e) {
                    // network error для same-origin = сервер не відповідає
                    throw new Error('Помилка з\'єднання з сервером (presign): ' + e.message);
                })
                .then(function (res) {
                    if (!res.ok) {
                        var msg = res.data && res.data.error ? res.data.error : 'Presign failed (HTTP ' + res.status + ')';
                        log('Presign FAILED: ' + msg);
                        throw new Error(msg);
                    }
                    var putUrl = res.data.url;
                    var key = res.data.key;
                    var contentType = (res.data.headers && res.data.headers['Content-Type']) ? res.data.headers['Content-Type'] : mime;

                    log('Presign OK. key=' + key);
                    log('PUT → ' + putUrl.slice(0, 120));
                    log('Content-Type → ' + contentType);
                    status.innerHTML = '<span class="text-info">⏳ PUT у S3 (origin: <b>' + window.location.origin + '</b>)...</span>';

                    // Крок 2: PUT на S3 (cross-origin, потрібен CORS на бакеті)
                    return fetch(putUrl, {
                        method: 'PUT',
                        body: file,
                        headers: { 'Content-Type': contentType },
                        mode: 'cors',
                        credentials: 'omit',
                    }).then(function (putRes) {
                        if (!putRes.ok) {
                            return putRes.text().then(function (errText) {
                                log('PUT FAILED ' + putRes.status + ': ' + errText);
                                throw new Error('S3 PUT ' + putRes.status + ': ' + (errText ? errText.slice(0, 300) : ''));
                            });
                        }
                        log('PUT OK → ' + key);
                        return key;
                    }).catch(function (e) {
                        if (e.message === 'Failed to fetch' || e.message.indexOf('NetworkError') !== -1) {
                            var origin = window.location.origin;
                            throw new Error(
                                'CORS заблокований: браузер відхилив PUT на S3.\n' +
                                'Origin: ' + origin + '\n' +
                                'Додайте цей origin у CORS бакета (AWS S3 → Permissions → CORS):\n' +
                                '"AllowedOrigins": ["' + origin + '"], "AllowedMethods": ["PUT"], "AllowedHeaders": ["*"]'
                            );
                        }
                        throw e;
                    });
                })
                .then(function (key) {
                    input.value = key;
                    log('Ключ записано в поле: ' + key);
                    status.innerHTML = '<span class="text-success">✅ Завантажено в S3: <code>' + key + '</code><br><b>Натисніть «Зберегти».</b></span>';
                    if (currentKeyDiv) {
                        currentKeyDiv.textContent = 'Поточний ключ: ' + key;
                    }
                    var showUrl = input.getAttribute('data-preview-base');
                    if (showUrl) {
                        preview.src = showUrl + '?key=' + encodeURIComponent(key);
                        preview.style.display = 'inline-block';
                    }
                })
                .catch(function (e) {
                    var msg = e.message || 'Невідома помилка';
                    log('ERROR: ' + msg);
                    status.innerHTML = '<span class="text-danger" style="white-space:pre-wrap">❌ ' + msg.replace(/</g, '&lt;') + '</span>';
                });
        });
    }

    var scanScheduled = false;
    function scheduleScan() {
        if (scanScheduled) {
            return;
        }
        scanScheduled = true;
        requestAnimationFrame(function () {
            scanScheduled = false;
            scan();
        });
    }

    function scan() {
        var inputs = document.querySelectorAll('input.ea-product-image-s3-key');
        if (inputs.length > 0) {
            log('scan: знайдено ' + inputs.length + ' поле(й) .ea-product-image-s3-key');
        }
        inputs.forEach(function (inp) {
            var row = inp.closest('.form-group') || inp.closest('.mb-3') || inp.closest('div');
            if (row) {
                initRow(row);
            } else {
                log('Поле знайдено, але немає батьківського контейнера (.form-group / .mb-3 / div)');
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scheduleScan);
    } else {
        scheduleScan();
    }

    document.addEventListener('turbo:load', scheduleScan);

    if (typeof MutationObserver !== 'undefined') {
        var moTimer = null;
        var mo = new MutationObserver(function () {
            clearTimeout(moTimer);
            moTimer = setTimeout(scheduleScan, 100);
        });
        mo.observe(document.documentElement, { childList: true, subtree: true });
    }
})();
