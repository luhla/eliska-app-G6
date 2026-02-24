/**
 * admin-audio.js
 * Handles:
 *  - Audio recording via MediaRecorder API
 *  - Webcam image capture
 *  - Remove image button
 */
(function () {
    'use strict';

    // ---------------------------------------------------------------
    // Remove image
    // ---------------------------------------------------------------
    const btnRemoveImage = document.getElementById('btn-remove-image');
    const previewWrap = document.getElementById('image-preview-wrap');
    const previewImg = document.getElementById('image-preview');
    const inputImageBase64 = document.querySelector('input[name="image_base64"]');
    const inputArasaacId = document.querySelector('input[name="arasaac_id"]');
    const imageFileInput = document.querySelector('[name="image_file"]');

    if (btnRemoveImage) {
        btnRemoveImage.addEventListener('click', function () {
            if (previewWrap) previewWrap.classList.add('d-none');
            if (previewImg) previewImg.src = '';
            if (inputImageBase64) inputImageBase64.value = '';
            if (inputArasaacId) inputArasaacId.value = '';
            if (imageFileInput) imageFileInput.value = '';
        });
    }

    // ---------------------------------------------------------------
    // Webcam capture
    // ---------------------------------------------------------------
    const btnWebcamStart = document.getElementById('btn-webcam-start');
    const btnWebcamCapture = document.getElementById('btn-webcam-capture');
    const btnWebcamStop = document.getElementById('btn-webcam-stop');
    const webcamVideo = document.getElementById('webcam-video');
    const webcamCanvas = document.getElementById('webcam-canvas');

    let webcamStream = null;

    if (btnWebcamStart) {
        btnWebcamStart.addEventListener('click', async function () {
            try {
                webcamStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
                webcamVideo.srcObject = webcamStream;
                webcamVideo.classList.remove('d-none');
                btnWebcamStart.classList.add('d-none');
                btnWebcamCapture.classList.remove('d-none');
                btnWebcamStop.classList.remove('d-none');
            } catch (e) {
                alert('Nelze spustit kameru: ' + e.message);
            }
        });
    }

    if (btnWebcamCapture) {
        btnWebcamCapture.addEventListener('click', function () {
            if (!webcamVideo) return;
            webcamCanvas.width = webcamVideo.videoWidth;
            webcamCanvas.height = webcamVideo.videoHeight;
            const ctx = webcamCanvas.getContext('2d');
            ctx.drawImage(webcamVideo, 0, 0);
            const dataUri = webcamCanvas.toDataURL('image/jpeg', 0.85);

            // Set preview
            if (previewImg) previewImg.src = dataUri;
            if (previewWrap) previewWrap.classList.remove('d-none');
            if (inputImageBase64) inputImageBase64.value = dataUri;
            if (inputArasaacId) inputArasaacId.value = '';

            // Stop camera
            stopWebcam();
        });
    }

    if (btnWebcamStop) {
        btnWebcamStop.addEventListener('click', stopWebcam);
    }

    function stopWebcam() {
        if (webcamStream) {
            webcamStream.getTracks().forEach(function (t) { t.stop(); });
            webcamStream = null;
        }
        if (webcamVideo) {
            webcamVideo.srcObject = null;
            webcamVideo.classList.add('d-none');
        }
        if (btnWebcamStart) btnWebcamStart.classList.remove('d-none');
        if (btnWebcamCapture) btnWebcamCapture.classList.add('d-none');
        if (btnWebcamStop) btnWebcamStop.classList.add('d-none');
    }

    // ---------------------------------------------------------------
    // Audio recording (MediaRecorder)
    // ---------------------------------------------------------------
    const btnRecordStart = document.getElementById('btn-record-start');
    const btnRecordStop = document.getElementById('btn-record-stop');
    const recordStatus = document.getElementById('record-status');
    const audioPreview = document.getElementById('audio-preview');
    const inputAudioBase64 = document.querySelector('input[name="audio_base64"]');

    let mediaRecorder = null;
    let audioChunks = [];
    let recordInterval = null;
    let recordSeconds = 0;

    if (btnRecordStart) {
        btnRecordStart.addEventListener('click', async function () {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                audioChunks = [];
                recordSeconds = 0;

                const options = {};
                if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) {
                    options.mimeType = 'audio/webm;codecs=opus';
                } else if (MediaRecorder.isTypeSupported('audio/ogg;codecs=opus')) {
                    options.mimeType = 'audio/ogg;codecs=opus';
                }

                mediaRecorder = new MediaRecorder(stream, options);

                mediaRecorder.ondataavailable = function (e) {
                    if (e.data.size > 0) audioChunks.push(e.data);
                };

                mediaRecorder.onstop = function () {
                    clearInterval(recordInterval);
                    if (recordStatus) recordStatus.textContent = '';

                    stream.getTracks().forEach(function (t) { t.stop(); });

                    const mime = mediaRecorder.mimeType || 'audio/webm';
                    const blob = new Blob(audioChunks, { type: mime });
                    const objectUrl = URL.createObjectURL(blob);

                    if (audioPreview) {
                        audioPreview.src = objectUrl;
                        audioPreview.classList.remove('d-none');
                    }

                    // Convert to base64 data URI for form submission
                    const reader = new FileReader();
                    reader.onloadend = function () {
                        if (inputAudioBase64) inputAudioBase64.value = reader.result;
                    };
                    reader.readAsDataURL(blob);

                    btnRecordStart.disabled = false;
                    btnRecordStop.classList.add('d-none');
                };

                mediaRecorder.start(100); // collect data every 100ms

                btnRecordStart.disabled = true;
                btnRecordStop.classList.remove('d-none');

                recordInterval = setInterval(function () {
                    recordSeconds++;
                    if (recordStatus) recordStatus.textContent = '⏺ Nahrávám ' + recordSeconds + 's…';
                }, 1000);

            } catch (e) {
                alert('Nelze spustit mikrofon: ' + e.message);
            }
        });
    }

    if (btnRecordStop) {
        btnRecordStop.addEventListener('click', function () {
            if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                mediaRecorder.stop();
            }
        });
    }

    // Update preview when file is selected (image)
    if (imageFileInput && previewImg) {
        imageFileInput.addEventListener('change', function () {
            const file = this.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = function (e) {
                previewImg.src = e.target.result;
                if (previewWrap) previewWrap.classList.remove('d-none');
                if (inputImageBase64) inputImageBase64.value = '';
                if (inputArasaacId) inputArasaacId.value = '';
            };
            reader.readAsDataURL(file);
        });
    }
})();
