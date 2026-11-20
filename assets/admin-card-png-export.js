(function () {
    'use strict';

    var widthInput = document.getElementById('card-png-width');
    var heightInput = document.getElementById('card-png-height');
    var gapInput = document.getElementById('card-png-gap');
    var dpiInput = document.getElementById('card-png-dpi');
    var status = document.getElementById('card-png-status');
    var buttons = Array.from(document.querySelectorAll('[data-card-png-face]'));
    var storageKey = 'student-id-card-png-export-settings';

    if (!widthInput || !heightInput || !gapInput || !dpiInput || !status || !buttons.length) {
        return;
    }

    function clampInput(input) {
        var value = Number(input.value);
        var minimum = Number(input.min);
        var maximum = Number(input.max);
        if (!Number.isFinite(value)) {
            return null;
        }
        return Math.max(minimum, Math.min(maximum, value));
    }

    function restoreSettings() {
        try {
            var saved = JSON.parse(window.localStorage.getItem(storageKey) || '{}');
            [['width', widthInput], ['height', heightInput], ['gap', gapInput]].forEach(function (entry) {
                var value = Number(saved[entry[0]]);
                if (Number.isFinite(value) && value >= Number(entry[1].min) && value <= Number(entry[1].max)) {
                    entry[1].value = String(value);
                }
            });
            if (saved.dpi === '300' || saved.dpi === '600') {
                dpiInput.value = saved.dpi;
            }
        } catch (error) {
            status.textContent = 'Export size settings could not be restored from this browser.';
        }
    }

    function saveSettings() {
        try {
            window.localStorage.setItem(storageKey, JSON.stringify({
                width: widthInput.value,
                height: heightInput.value,
                gap: gapInput.value,
                dpi: dpiInput.value
            }));
        } catch (error) {
            status.textContent = 'Export size settings could not be saved in this browser.';
        }
    }

    restoreSettings();
    [widthInput, heightInput, gapInput, dpiInput].forEach(function (input) {
        input.addEventListener('change', saveSettings);
    });

    var resourceCache = new Map();
    async function resourceAsDataUrl(url) {
        if (url.startsWith('data:')) {
            return url;
        }
        if (!resourceCache.has(url)) {
            resourceCache.set(url, fetch(url, { credentials: 'same-origin' }).then(function (response) {
                if (!response.ok) {
                    throw new Error('A card image could not be loaded for PNG export.');
                }
                return response.blob().then(function (blob) {
                    if (!['image/png', 'image/jpeg', 'image/webp', 'image/gif'].includes(blob.type)) {
                        throw new Error('A card image uses an unsupported format for PNG export.');
                    }
                    return blob;
                });
            }).then(function (blob) {
                return new Promise(function (resolve, reject) {
                    var reader = new FileReader();
                    reader.onload = function () {
                        if (typeof reader.result !== 'string') {
                            reject(new Error('A card image could not be prepared for PNG export.'));
                            return;
                        }
                        resolve(reader.result);
                    };
                    reader.onerror = function () {
                        reject(new Error('A card image could not be prepared for PNG export.'));
                    };
                    reader.readAsDataURL(blob);
                });
            }));
        }
        return resourceCache.get(url);
    }

    function loadImage(url) {
        return new Promise(function (resolve, reject) {
            var image = new Image();
            image.onload = function () { resolve(image); };
            image.onerror = function () { reject(new Error('A card image could not be loaded for PNG export.')); };
            image.src = url;
        });
    }

    function roundedPath(context, rect, radius) {
        var r = Math.max(0, Math.min(radius, rect.width / 2, rect.height / 2));
        context.beginPath();
        context.moveTo(rect.left + r, rect.top);
        context.arcTo(rect.right, rect.top, rect.right, rect.top + r, r);
        context.arcTo(rect.right, rect.bottom, rect.right - r, rect.bottom, r);
        context.arcTo(rect.left, rect.bottom, rect.left, rect.bottom - r, r);
        context.arcTo(rect.left, rect.top, rect.left + r, rect.top, r);
        context.closePath();
    }

    function paintBackgroundColor(context, rect, style) {
        var color = style.getPropertyValue('background-color');
        if (!color || color === 'transparent' || color === 'rgba(0, 0, 0, 0)') {
            return;
        }
        context.fillStyle = color;
        var radius = parseFloat(style.getPropertyValue('border-top-left-radius')) || 0;
        if (radius) {
            roundedPath(context, rect, radius);
            context.fill();
        } else {
            context.fillRect(rect.left, rect.top, rect.width, rect.height);
        }
    }

    function backgroundSize(size, image, rect) {
        var values = size.trim().split(/\s+/);
        function parse(value, base, natural) {
            if (!value || value === 'auto') { return natural; }
            if (value.endsWith('%')) { return base * parseFloat(value) / 100; }
            if (value.endsWith('px')) { return parseFloat(value); }
            return natural;
        }
        var width = parse(values[0], rect.width, image.naturalWidth);
        var height = parse(values[1], rect.height, values[0] && values[0].endsWith('%')
            ? rect.height
            : image.naturalHeight * width / image.naturalWidth);
        if (values.length === 1 && values[0] !== 'auto') {
            height = image.naturalHeight * width / image.naturalWidth;
        }
        return [Math.max(1, width), Math.max(1, height)];
    }

    function backgroundStart(position, freeSpace, direction) {
        if (position === 'center') { return freeSpace / 2; }
        if (position === 'right' || position === 'bottom') { return freeSpace; }
        if (position.endsWith('%')) { return freeSpace * parseFloat(position) / 100; }
        if (position.endsWith('px')) {
            var offset = parseFloat(position);
            return direction === 'end' ? freeSpace - offset : offset;
        }
        return 0;
    }

    async function paintBackgroundImage(context, rect, style, imageValue, opacity) {
        var matches = Array.from(imageValue.matchAll(/url\((["']?)(.*?)\1\)/g));
        for (var matchIndex = matches.length - 1; matchIndex >= 0; matchIndex--) {
            var url = matches[matchIndex][2];
            var image = await loadImage(await resourceAsDataUrl(new URL(url, document.baseURI).href));
            var sizes = backgroundSize(style.getPropertyValue('background-size') || 'auto', image, rect);
            var sizeX = sizes[0];
            var sizeY = sizes[1];
            if (!Number.isFinite(sizeX) || !Number.isFinite(sizeY) || sizeX < 1 || sizeY < 1) {
                throw new Error('A card background image has invalid dimensions for PNG export.');
            }
            var repeat = (style.getPropertyValue('background-repeat') || 'repeat').split(/\s+/);
            var repeatsX = ['repeat', 'repeat-x', 'space', 'round'].includes(repeat[0]);
            var repeatsY = ['repeat', 'repeat-y', 'space', 'round'].includes(repeat[1] || repeat[0]);
            var position = (style.getPropertyValue('background-position') || '0% 0%').split(/\s+/);
            var x = rect.left + backgroundStart(position[0] || '0%', rect.width - sizeX, 'end');
            var y = rect.top + backgroundStart(position[1] || '0%', rect.height - sizeY, 'end');
            context.save();
            context.globalAlpha *= opacity;
            context.beginPath();
            context.rect(rect.left, rect.top, rect.width, rect.height);
            context.clip();
            var firstX = repeatsX ? rect.left + ((x - rect.left) % sizeX) - sizeX : x;
            var firstY = repeatsY ? rect.top + ((y - rect.top) % sizeY) - sizeY : y;
            var rowCount = 0;
            for (var drawY = firstY; drawY < rect.bottom && rowCount < 128; drawY += repeatsY ? sizeY : rect.height + sizeY, rowCount++) {
                var columnCount = 0;
                for (var drawX = firstX; drawX < rect.right && columnCount < 128; drawX += repeatsX ? sizeX : rect.width + sizeX, columnCount++) {
                    context.drawImage(image, drawX, drawY, sizeX, sizeY);
                    if (!repeatsX) { break; }
                }
                if (!repeatsY) { break; }
            }
            context.restore();
        }
    }

    async function paintPseudoElement(context, element, cardRect, pseudo) {
        var style = window.getComputedStyle(element, pseudo);
        var content = style.getPropertyValue('content');
        if (!content || content === 'none' || content === 'normal'
            || style.getPropertyValue('display') === 'none') {
            return;
        }
        var hostRect = element.getBoundingClientRect();
        var leftOffset = parseFloat(style.getPropertyValue('left'));
        var rightOffset = parseFloat(style.getPropertyValue('right'));
        var topOffset = parseFloat(style.getPropertyValue('top'));
        var bottomOffset = parseFloat(style.getPropertyValue('bottom'));
        var width = parseFloat(style.getPropertyValue('width'));
        var height = parseFloat(style.getPropertyValue('height'));
        width = Number.isFinite(width) && width > 0
            ? width
            : Math.max(0, hostRect.width - (Number.isFinite(leftOffset) ? leftOffset : 0) - (Number.isFinite(rightOffset) ? rightOffset : 0));
        height = Number.isFinite(height) && height > 0
            ? height
            : Math.max(0, hostRect.height - (Number.isFinite(topOffset) ? topOffset : 0) - (Number.isFinite(bottomOffset) ? bottomOffset : 0));
        var left = Number.isFinite(leftOffset)
            ? hostRect.left + leftOffset
            : (Number.isFinite(rightOffset) ? hostRect.right - rightOffset - width : hostRect.left);
        var top = Number.isFinite(topOffset)
            ? hostRect.top + topOffset
            : (Number.isFinite(bottomOffset) ? hostRect.bottom - bottomOffset - height : hostRect.top);
        if (width <= 0 || height <= 0) { return; }
        var rect = { left: left, top: top, right: left + width, bottom: top + height, width: width, height: height };
        context.save();
        var opacity = parseFloat(style.getPropertyValue('opacity'));
        context.globalAlpha *= Number.isFinite(opacity) ? opacity : 1;
        paintBackgroundColor(context, rect, style);
        var image = style.getPropertyValue('background-image');
        if (image && image !== 'none') {
            await paintBackgroundImage(context, rect, style, image, 1);
        }
        context.restore();
    }

    function paintBorder(context, rect, style) {
        var widths = [
            parseFloat(style.getPropertyValue('border-top-width')) || 0,
            parseFloat(style.getPropertyValue('border-right-width')) || 0,
            parseFloat(style.getPropertyValue('border-bottom-width')) || 0,
            parseFloat(style.getPropertyValue('border-left-width')) || 0
        ];
        var colors = [
            style.getPropertyValue('border-top-color'),
            style.getPropertyValue('border-right-color'),
            style.getPropertyValue('border-bottom-color'),
            style.getPropertyValue('border-left-color')
        ];
        context.save();
        context.fillStyle = colors[0];
        if (widths[0]) { context.fillRect(rect.left, rect.top, rect.width, widths[0]); }
        context.fillStyle = colors[1];
        if (widths[1]) { context.fillRect(rect.right - widths[1], rect.top, widths[1], rect.height); }
        context.fillStyle = colors[2];
        if (widths[2]) { context.fillRect(rect.left, rect.bottom - widths[2], rect.width, widths[2]); }
        context.fillStyle = colors[3];
        if (widths[3]) { context.fillRect(rect.left, rect.top, widths[3], rect.height); }
        context.restore();
    }

    function paintText(context, node, element, cardRect) {
        var value = node.nodeValue;
        if (!value || !value.trim()) { return; }
        var range = document.createRange();
        range.selectNodeContents(node);
        var rects = Array.from(range.getClientRects());
        var style = window.getComputedStyle(element);
        var fontSize = parseFloat(style.getPropertyValue('font-size')) || 12;
        var lineHeight = parseFloat(style.getPropertyValue('line-height')) || fontSize * 1.2;
        context.font = style.getPropertyValue('font') || (style.getPropertyValue('font-weight') + ' ' + fontSize + 'px ' + style.getPropertyValue('font-family'));
        context.fillStyle = style.getPropertyValue('color') || '#000';
        context.textBaseline = 'middle';
        context.textAlign = style.getPropertyValue('text-align') || 'left';
        var letterSpacing = parseFloat(style.getPropertyValue('letter-spacing')) || 0;
        rects.forEach(function (textRect) {
            var text = value;
            var x = textRect.left;
            if (context.textAlign === 'center') { x = textRect.left + textRect.width / 2; }
            else if (context.textAlign === 'right' || context.textAlign === 'end') { x = textRect.right; }
            if (style.getPropertyValue('text-transform') === 'uppercase') { text = text.toUpperCase(); }
            else if (style.getPropertyValue('text-transform') === 'lowercase') { text = text.toLowerCase(); }
            context.save();
            context.beginPath();
            context.rect(cardRect.left, cardRect.top, cardRect.width, cardRect.height);
            context.clip();
            var baseline = textRect.top + Math.min(lineHeight, textRect.height || lineHeight) / 2;
            if (letterSpacing === 0) {
                context.fillText(text, x, baseline);
            } else {
                var cursor = x;
                if (context.textAlign === 'right' || context.textAlign === 'end') {
                    cursor -= Array.from(text).reduce(function (total, character) { return total + context.measureText(character).width + letterSpacing; }, 0);
                } else if (context.textAlign === 'center') {
                    cursor -= Array.from(text).reduce(function (total, character) { return total + context.measureText(character).width + letterSpacing; }, 0) / 2;
                }
                Array.from(text).forEach(function (character) {
                    context.fillText(character, cursor, baseline);
                    cursor += context.measureText(character).width + letterSpacing;
                });
            }
            context.restore();
        });
    }

    async function paintElement(context, element, cardRect) {
        var style = window.getComputedStyle(element);
        if (style.getPropertyValue('display') === 'none' || style.getPropertyValue('visibility') === 'hidden'
            || parseFloat(style.getPropertyValue('opacity')) === 0) {
            return;
        }
        var rect = element.getBoundingClientRect();
        if (rect.width <= 0 || rect.height <= 0) { return; }
        var box = { left: rect.left, top: rect.top, right: rect.right, bottom: rect.bottom, width: rect.width, height: rect.height };
        context.save();
        var opacity = parseFloat(style.getPropertyValue('opacity'));
        context.globalAlpha *= Number.isFinite(opacity) ? opacity : 1;
        if (style.getPropertyValue('overflow') === 'hidden' || style.getPropertyValue('overflow') === 'clip') {
            var radius = parseFloat(style.getPropertyValue('border-top-left-radius')) || 0;
            if (radius) {
                roundedPath(context, box, radius);
            } else {
                context.beginPath();
                context.rect(box.left, box.top, box.width, box.height);
            }
            context.clip();
        }
        paintBackgroundColor(context, box, style);
        var backgroundImage = style.getPropertyValue('background-image');
        if (backgroundImage && backgroundImage !== 'none') {
            await paintBackgroundImage(context, box, style, backgroundImage, 1);
        }
        await paintPseudoElement(context, element, cardRect, '::before');

        if (element instanceof HTMLImageElement) {
            var image = await loadImage(await resourceAsDataUrl(element.currentSrc || element.src));
            var objectFit = style.getPropertyValue('object-fit');
            if (objectFit === 'cover') {
                var sourceWidth = image.naturalWidth;
                var sourceHeight = image.naturalHeight;
                var scale = Math.max(box.width / sourceWidth, box.height / sourceHeight);
                var cropWidth = box.width / scale;
                var cropHeight = box.height / scale;
                var position = style.getPropertyValue('object-position').split(/\s+/);
                var cropX = (sourceWidth - cropWidth) * (parseFloat(position[0]) / 100 || 0);
                var cropY = (sourceHeight - cropHeight) * (parseFloat(position[1]) / 100 || 0);
                context.drawImage(image, cropX, cropY, cropWidth, cropHeight, box.left, box.top, box.width, box.height);
            } else if (objectFit === 'contain') {
                var containScale = Math.min(box.width / image.naturalWidth, box.height / image.naturalHeight);
                var drawWidth = image.naturalWidth * containScale;
                var drawHeight = image.naturalHeight * containScale;
                var objectPosition = style.getPropertyValue('object-position').trim().split(/\s+/);
                function positionOffset(value, freeSpace, start, end) {
                    if (value === end) { return freeSpace; }
                    if (value === 'center') { return freeSpace / 2; }
                    if (value === start) { return 0; }
                    if (value && value.endsWith('%')) { return freeSpace * (parseFloat(value) / 100); }
                    if (value && value.endsWith('px')) { return parseFloat(value); }
                    return freeSpace / 2;
                }
                var drawX = box.left + positionOffset(objectPosition[0] || '50%', box.width - drawWidth, 'left', 'right');
                var drawY = box.top + positionOffset(objectPosition[1] || '50%', box.height - drawHeight, 'top', 'bottom');
                context.drawImage(image, drawX, drawY, drawWidth, drawHeight);
            } else {
                context.drawImage(image, box.left, box.top, box.width, box.height);
            }
        } else if (element instanceof SVGElement && element.tagName.toLowerCase() === 'svg') {
            var svgClone = element.cloneNode(true);
            svgClone.setAttribute('width', String(box.width));
            svgClone.setAttribute('height', String(box.height));
            var svgText = new XMLSerializer().serializeToString(svgClone);
            var svgData = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svgText);
            var svgImage = await loadImage(svgData);
            context.drawImage(svgImage, box.left, box.top, box.width, box.height);
        } else {
            for (var index = 0; index < element.childNodes.length; index++) {
                var child = element.childNodes[index];
                if (child.nodeType === Node.TEXT_NODE) {
                    paintText(context, child, element, cardRect);
                } else if (child.nodeType === Node.ELEMENT_NODE) {
                    await paintElement(context, child, cardRect);
                }
            }
        }
        await paintPseudoElement(context, element, cardRect, '::after');
        paintBorder(context, box, style);
        context.restore();
    }

    async function drawCard(context, card, rect, x, y, tileWidth, tileHeight) {
        var scale = Math.min(tileWidth / rect.height, tileHeight / rect.width);
        context.save();
        context.translate(x + tileWidth, y);
        context.rotate(Math.PI / 2);
        context.translate((tileHeight - rect.width * scale) / 2, (tileWidth - rect.height * scale) / 2);
        context.scale(scale, scale);
        context.translate(-rect.left, -rect.top);
        await paintElement(context, card, rect);
        context.restore();
    }

    function crc32(bytes) {
        var crc = 0xFFFFFFFF;
        for (var index = 0; index < bytes.length; index++) {
            crc ^= bytes[index];
            for (var bit = 0; bit < 8; bit++) {
                crc = (crc >>> 1) ^ ((crc & 1) ? 0xEDB88320 : 0);
            }
        }
        return (crc ^ 0xFFFFFFFF) >>> 0;
    }

    function pngChunk(type, data) {
        var typeBytes = new TextEncoder().encode(type);
        var crcInput = new Uint8Array(typeBytes.length + data.length);
        crcInput.set(typeBytes);
        crcInput.set(data, typeBytes.length);
        var chunk = new Uint8Array(12 + data.length);
        var view = new DataView(chunk.buffer);
        view.setUint32(0, data.length);
        chunk.set(typeBytes, 4);
        chunk.set(data, 8);
        view.setUint32(8 + data.length, crc32(crcInput));
        return chunk;
    }

    async function setPngResolution(blob, dpi) {
        var bytes = new Uint8Array(await blob.arrayBuffer());
        var signature = [137, 80, 78, 71, 13, 10, 26, 10];
        if (bytes.length < 33 || signature.some(function (byte, index) { return bytes[index] !== byte; })) {
            throw new Error('The browser did not produce a valid PNG image.');
        }
        var pixelsPerMeter = Math.round(dpi / 0.0254);
        var density = new Uint8Array(9);
        var densityView = new DataView(density.buffer);
        densityView.setUint32(0, pixelsPerMeter);
        densityView.setUint32(4, pixelsPerMeter);
        density[8] = 1;
        var densityChunk = pngChunk('pHYs', density);
        var parts = [bytes.slice(0, 8)];
        var offset = 8;
        var inserted = false;
        while (offset + 12 <= bytes.length) {
            var length = new DataView(bytes.buffer, bytes.byteOffset + offset, 4).getUint32(0);
            var end = offset + 12 + length;
            if (end > bytes.length) {
                throw new Error('The generated PNG data is incomplete.');
            }
            var type = String.fromCharCode(bytes[offset + 4], bytes[offset + 5], bytes[offset + 6], bytes[offset + 7]);
            if (type !== 'pHYs') {
                parts.push(bytes.slice(offset, end));
            }
            if (type === 'IHDR') {
                parts.push(densityChunk);
                inserted = true;
            }
            offset = end;
            if (type === 'IEND') {
                break;
            }
        }
        if (!inserted) {
            throw new Error('The PNG resolution could not be set.');
        }
        return new Blob(parts, { type: 'image/png' });
    }

    function canvasBlob(canvas) {
        return new Promise(function (resolve, reject) {
            canvas.toBlob(function (blob) {
                if (!blob) {
                    reject(new Error('The browser could not render the card PNG.'));
                    return;
                }
                resolve(blob);
            }, 'image/png');
        });
    }

    async function makeFacePng(face) {
        if (document.getElementById('card-number-print-warning')) {
            throw new Error('PNG export is disabled because a selected card has no card number. Add the card number and reload the preview.');
        }
        var prefix = face === 'front' ? 'Front' : 'Back';
        var cards = Array.from(document.querySelectorAll('.admin-card-print[aria-label^="' + prefix + ' of student ID card for "]'));
        if (!cards.length) {
            throw new Error('The selected card side is not available in this preview. Reload it and try again.');
        }
        if (cards.length > 2) {
            throw new Error('PNG export supports at most two cards at a time.');
        }

        var cardWidthMm = clampInput(widthInput);
        var cardHeightMm = clampInput(heightInput);
        var gapMm = clampInput(gapInput);
        var dpi = Number(dpiInput.value);
        if (cardWidthMm === null || cardHeightMm === null || gapMm === null
            || dpiInput.value !== '300' && dpiInput.value !== '600') {
            throw new Error('Enter valid card dimensions and choose 300 or 600 DPI.');
        }
        widthInput.value = String(cardWidthMm);
        heightInput.value = String(cardHeightMm);
        gapInput.value = String(gapMm);
        saveSettings();

        if (document.fonts && document.fonts.ready) {
            await Promise.race([
                document.fonts.ready,
                new Promise(function (resolve) { window.setTimeout(resolve, 2000); })
            ]);
        }
        var physicalWidth = Math.round(cardWidthMm * dpi / 25.4);
        var physicalHeight = Math.round(cardHeightMm * dpi / 25.4);
        var gap = Math.round(gapMm * dpi / 25.4);
        var canvas = document.createElement('canvas');
        var tileWidth = physicalHeight;
        var tileHeight = physicalWidth;
        canvas.width = cards.length * tileWidth + (cards.length - 1) * gap;
        canvas.height = tileHeight;
        if (canvas.width > 10000 || canvas.height > 10000 || canvas.width * canvas.height > 40000000) {
            throw new Error('This PNG would be too large. Choose 300 DPI or smaller card dimensions.');
        }
        var context = canvas.getContext('2d');
        if (!context) {
            throw new Error('The browser could not create an image canvas.');
        }
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, canvas.width, canvas.height);

        for (var index = 0; index < cards.length; index++) {
            var card = cards[index];
            var rect = card.getBoundingClientRect();
            if (rect.width < 1 || rect.height < 1) {
                throw new Error('A card is not visible in the preview. Reload the page and try again.');
            }
            await drawCard(context, card, rect, index * (tileWidth + gap), 0, tileWidth, tileHeight);
        }

        return setPngResolution(await canvasBlob(canvas), dpi);
    }

    buttons.forEach(function (button) {
        button.addEventListener('click', async function () {
            if (buttons.some(function (item) { return item.disabled; })) {
                return;
            }
            buttons.forEach(function (item) { item.disabled = true; });
            status.textContent = 'Preparing PNG...';
            try {
                var face = button.getAttribute('data-card-png-face');
                var png = await makeFacePng(face);
                var downloadUrl = URL.createObjectURL(png);
                var link = document.createElement('a');
                link.href = downloadUrl;
                link.download = 'student-cards-' + face + '-' + new Date().toISOString().slice(0, 10) + '.png';
                link.click();
                window.setTimeout(function () { URL.revokeObjectURL(downloadUrl); }, 1000);
                status.textContent = (face === 'front' ? 'Front' : 'Back') + ' PNG ready at the selected card size.';
            } catch (error) {
                status.textContent = error.message || 'The PNG could not be generated.';
            } finally {
                buttons.forEach(function (item) { item.disabled = false; });
            }
        });
    });
})();
