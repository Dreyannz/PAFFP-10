(() => {
    "use strict";

    const cameraModalElement = document.getElementById("cameraModal");
    if (!cameraModalElement) return;

    const modal = bootstrap.Modal.getOrCreateInstance(cameraModalElement);
    const uploadSuccessElement = document.getElementById("uploadSuccessModal");
    const uploadSuccessModal = uploadSuccessElement
        ? bootstrap.Modal.getOrCreateInstance(uploadSuccessElement)
        : null;

    const video = document.getElementById("cameraVideo");
    const canvas = document.getElementById("cameraCanvas");
    const image = document.getElementById("capturedImage");
    const cameraContainer = document.getElementById("cameraContainer");
    const imageContainer = document.getElementById("imageContainer");
    const previewContainer = document.getElementById("previewContainer");
    const captureBtn = document.getElementById("captureBtn");
    const retakeBtn = document.getElementById("retakeBtn");
    const nextBtn = document.getElementById("nextBtn");
    const captureButton = captureBtn?.querySelector("button");
    const retakeButton = retakeBtn?.querySelector("button");
    const nextButton = nextBtn?.querySelector("button");
    const cameraDropdownButton = document.getElementById("cameraDropdownButton");
    const cameraList = document.getElementById("cameraList");
    const cameraStatus = document.getElementById("cameraStatus");
    const closeCaptureModal = document.getElementById("closeCaptureModal");

    const reviewContainer = document.getElementById("reviewContainer");
    const reviewBtnContainer = document.getElementById("reviewBtnContainer");
    const reviewRetakeAll = document.getElementById("reviewRetakeAll");
    const reviewSave = document.getElementById("reviewSave");
    const reviewRetakeButton = reviewRetakeAll?.querySelector("button");
    const reviewSaveButton = reviewSave?.querySelector("button");
    const reviewRetakeOneButtons = Array.from(
        cameraModalElement.querySelectorAll(".review-retake-one")
    );
    const uploadProgressContainer = document.getElementById("uploadProgressContainer");
    const cameraControlPanel = cameraModalElement.querySelector(".camera-control-panel");

    const defaultCamera = "selectedCamera";
    let stream = null;
    let currentStep = 0;
    let cameraReady = false;
    let uploading = false;
    let initializationToken = 0;
    let returnToReviewAfterRetake = false;

    const photos = {
        farmer: null,
        farmerWithID: null,
        idFront: null,
        idBack: null
    };

    const steps = [
        { key: "farmer", title: "Farmer Photo", aspect: "square" },
        { key: "farmerWithID", title: "Farmer Photo with ID", aspect: "square" },
        { key: "idFront", title: "Farmer ID (Front)", aspect: "landscape" },
        { key: "idBack", title: "Farmer ID (Back)", aspect: "landscape" }
    ];

    function setCameraReviewMode(isReview) {
        cameraModalElement.classList.toggle("review-mode", isReview);
        cameraModalElement.classList.toggle("capture-mode", !isReview);
    }

    function setCapturedPreviewMode(isCaptured) {
        cameraModalElement.classList.toggle("captured-preview-mode", isCaptured);
    }

    function setStatus(message, state = "info", spinning = false) {
        if (!cameraStatus) return;

        const icons = {
            info: spinning ? "fa-solid fa-circle-notch fa-spin" : "fa-solid fa-circle-info",
            ready: "fa-solid fa-circle-check",
            warning: "fa-solid fa-triangle-exclamation",
            error: "fa-solid fa-circle-exclamation"
        };

        cameraStatus.className = "camera-status";
        if (state !== "info") {
            cameraStatus.classList.add(`is-${state}`);
        }

        cameraStatus.innerHTML = `
            <i class="${icons[state] || icons.info}"></i>
            <span>${escapeHtml(message)}</span>
        `;
    }

    function escapeHtml(value) {
        return String(value)
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll('"', "&quot;")
            .replaceAll("'", "&#039;");
    }

    function setDisabled(button, disabled) {
        if (!button) return;
        button.disabled = Boolean(disabled);
        button.setAttribute("aria-disabled", disabled ? "true" : "false");
    }

    function updateActionState() {
        const hasPhoto = Boolean(photos[steps[currentStep]?.key]);

        setDisabled(captureButton, !cameraReady || hasPhoto || uploading);
        setDisabled(retakeButton, !hasPhoto || uploading);
        setDisabled(nextButton, !hasPhoto || uploading);

        if (cameraDropdownButton) {
            cameraDropdownButton.disabled = !cameraReady || uploading;
        }
    }

    function setUploadLock(lock) {
        uploading = Boolean(lock);
        setDisabled(reviewRetakeButton, uploading);
        setDisabled(reviewSaveButton, uploading);

        reviewRetakeOneButtons.forEach(button => {
            setDisabled(button, uploading);
        });

        if (closeCaptureModal) {
            closeCaptureModal.disabled = uploading;
            closeCaptureModal.setAttribute("aria-disabled", uploading ? "true" : "false");
        }

        updateActionState();
    }

    function setFarmerName() {
        const first = document.getElementById("farmerFirstName")?.textContent.trim() || "";
        const middle = document.getElementById("farmerMiddleName")?.textContent.trim() || "";
        const last = document.getElementById("farmerLastName")?.textContent.trim() || "";
        const ext = document.getElementById("farmerExtName")?.textContent.trim() || "";
        const fullname = [first, middle, last, ext].filter(Boolean).join(" ");

        const title = document.getElementById("cameraModalTitle");
        if (title) {
            title.textContent = fullname || "Photo Capture";
            title.title = fullname || "Photo Capture";
        }
    }

    function syncCameraPreviewDimensions() {
        if (!previewContainer) return;

        const rect = previewContainer.getBoundingClientRect();
        const width = Math.round(rect.width);
        const height = Math.round(rect.height);

        if (!width || !height) return;

        [cameraContainer, imageContainer, video, image].forEach(element => {
            if (!element) return;

            element.style.setProperty("width", `${width}px`, "important");
            element.style.setProperty("height", `${height}px`, "important");
            element.style.setProperty("min-width", `${width}px`, "important");
            element.style.setProperty("min-height", `${height}px`, "important");
            element.style.setProperty("max-width", `${width}px`, "important");
            element.style.setProperty("max-height", `${height}px`, "important");
        });
    }

    function resizeCameraPreview() {
        const modalHeader = cameraModalElement.querySelector(".modal-header");
        const modalBody = cameraModalElement.querySelector(".modal-body");

        if (!previewContainer || !modalBody || !modalHeader) return;

        // Final-review mode uses a scrollable body rather than the live preview sizing.
        if (cameraModalElement.classList.contains("review-mode")) {
            return;
        }

        if (getComputedStyle(previewContainer).display === "none") return;

        const viewportHeight = window.visualViewport?.height || window.innerHeight;
        const viewportWidth = window.visualViewport?.width || window.innerWidth;
        const isSmallScreen = viewportWidth < 768;

        const outerGap = isSmallScreen ? 6 : 16;
        const headerHeight = modalHeader.getBoundingClientRect().height || 0;
        const heading = cameraModalElement.querySelector(".capture-stage-heading");
        const controls = cameraControlPanel;

        const bodyStyle = getComputedStyle(modalBody);
        const bodyVerticalPadding =
            parseFloat(bodyStyle.paddingTop || 0) +
            parseFloat(bodyStyle.paddingBottom || 0);
        const bodyHorizontalPadding =
            parseFloat(bodyStyle.paddingLeft || 0) +
            parseFloat(bodyStyle.paddingRight || 0);

        const headingHeight =
            heading && getComputedStyle(heading).display !== "none"
                ? heading.getBoundingClientRect().height
                : 0;

        const controlsHeight =
            controls && getComputedStyle(controls).display !== "none"
                ? controls.getBoundingClientRect().height
                : 0;

        const headingMargin =
            heading ? parseFloat(getComputedStyle(heading).marginBottom || 0) : 0;

        const controlsMargin =
            controls ? parseFloat(getComputedStyle(controls).marginTop || 0) : 0;

        const availableBodyHeight =
            viewportHeight - headerHeight - outerGap;

        const remainingHeight =
            availableBodyHeight -
            bodyVerticalPadding -
            headingHeight -
            headingMargin -
            controlsHeight -
            controlsMargin -
            8;

        const modalDialog = cameraModalElement.querySelector(".modal-dialog");
        const dialogWidth = modalDialog?.getBoundingClientRect().width || viewportWidth;

        const remainingWidth =
            dialogWidth -
            bodyHorizontalPadding -
            (isSmallScreen ? 8 : 24);

        const minPreview = isSmallScreen ? 90 : 120;
        const previewSize = Math.max(
            minPreview,
            Math.min(650, remainingHeight, remainingWidth)
        );

        cameraModalElement.style.setProperty(
            "--camera-preview-size",
            `${Math.floor(previewSize)}px`
        );

        requestAnimationFrame(syncCameraPreviewDimensions);
    }

    function updateStep() {
        setCameraReviewMode(false);
        setCapturedPreviewMode(false);

        const step = steps[currentStep];
        const captureTitle = document.getElementById("captureTitle");
        const guide = document.getElementById("captureGuide");

        if (captureTitle) captureTitle.textContent = step.title;
        if (guide) guide.style.display = step.aspect === "landscape" ? "block" : "none";

        if (reviewContainer) reviewContainer.style.display = "none";
        if (previewContainer) previewContainer.style.display = "block";
        if (imageContainer) imageContainer.style.display = "none";
        if (cameraContainer) cameraContainer.style.display = "block";

        if (captureBtn) captureBtn.style.display = "";
        if (retakeBtn) retakeBtn.style.display = "none";
        if (nextBtn) nextBtn.style.display = "";

        if (nextButton) {
            const label = nextButton.querySelector("span");
            const icon = nextButton.querySelector("i");
            const goesToReview =
                returnToReviewAfterRetake ||
                currentStep === steps.length - 1;

            if (label) {
                label.textContent = goesToReview ? "Review" : "Next";
            }

            if (icon) {
                icon.className = goesToReview
                    ? "fa-solid fa-images"
                    : "fa-solid fa-arrow-right";
            }
        }

        updateActionState();
        requestAnimationFrame(resizeCameraPreview);
    }

    function stopCamera() {
        if (stream) {
            stream.getTracks().forEach(track => track.stop());
            stream = null;
        }

        cameraReady = false;

        if (video) {
            video.srcObject = null;
        }

        updateActionState();
    }

    function cameraErrorMessage(error) {
        if (!error) return "Unable to start the camera.";

        if (error.name === "NotAllowedError" || error.name === "SecurityError") {
            return "Camera access is blocked. Allow camera permission in the browser and try again.";
        }

        if (error.name === "NotFoundError" || error.name === "DevicesNotFoundError") {
            return "No camera was detected on this device.";
        }

        if (error.name === "NotReadableError" || error.name === "TrackStartError") {
            return "The camera is already in use by another application or browser tab.";
        }

        if (error.name === "OverconstrainedError") {
            return "The selected camera is not available. Choose another camera.";
        }

        return "Unable to start the camera. Check browser permission and camera availability.";
    }

    async function startCamera(deviceId = null, quiet = false) {
        if (!navigator.mediaDevices?.getUserMedia) {
            cameraReady = false;
            setStatus(
                "Camera access requires a supported browser over HTTPS or localhost.",
                "error"
            );
            updateActionState();
            return false;
        }

        stopCamera();
        setStatus("Starting camera…", "info", true);

        if (cameraDropdownButton) cameraDropdownButton.disabled = true;

        const videoConstraints = deviceId
            ? {
                deviceId: { exact: deviceId },
                width: { ideal: 1920 },
                height: { ideal: 1080 }
            }
            : {
                facingMode: { ideal: "environment" },
                width: { ideal: 1920 },
                height: { ideal: 1080 }
            };

        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: videoConstraints,
                audio: false
            });

            if (!video) throw new Error("Camera video element is missing.");

            video.srcObject = stream;

            if (video.readyState < 1) {
                await new Promise((resolve, reject) => {
                    const timer = window.setTimeout(
                        () => reject(new Error("Camera metadata timed out.")),
                        10000
                    );

                    video.addEventListener("loadedmetadata", () => {
                        window.clearTimeout(timer);
                        resolve();
                    }, { once: true });
                });
            }

            await video.play();

            if (!video.videoWidth || !video.videoHeight) {
                throw new Error("Camera returned an empty video stream.");
            }

            cameraReady = true;

            const activeTrack = stream.getVideoTracks()[0];
            const activeId = activeTrack?.getSettings?.().deviceId || "";

            if (activeId) {
                localStorage.setItem(defaultCamera, activeId);
            }

            setStatus("Camera ready.", "ready");
            updateActionState();
            requestAnimationFrame(resizeCameraPreview);

            return true;
        } catch (error) {
            console.error("Camera start failed:", error);
            stopCamera();
            setStatus(cameraErrorMessage(error), "error");

            if (!quiet && cameraDropdownButton) {
                cameraDropdownButton.textContent = "Camera unavailable";
            }

            return false;
        }
    }

    async function refreshCameraList() {
        if (!cameraList || !cameraDropdownButton || !navigator.mediaDevices?.enumerateDevices) {
            return;
        }

        try {
            const devices = await navigator.mediaDevices.enumerateDevices();
            const cameras = devices.filter(device => device.kind === "videoinput");
            cameraList.innerHTML = "";

            if (!cameras.length) {
                cameraDropdownButton.textContent = "No Camera Found";
                cameraDropdownButton.disabled = true;
                setStatus("No camera was detected on this device.", "error");
                return;
            }

            const activeId =
                stream?.getVideoTracks?.()[0]?.getSettings?.().deviceId || "";

            cameras.forEach((camera, index) => {
                const li = document.createElement("li");
                const button = document.createElement("button");

                button.type = "button";
                button.className = "dropdown-item";
                button.dataset.deviceId = camera.deviceId;
                button.textContent = camera.label || `Camera ${index + 1}`;

                if (camera.deviceId && camera.deviceId === activeId) {
                    button.classList.add("active");
                    cameraDropdownButton.textContent = button.textContent;
                }

                button.addEventListener("click", async () => {
                    if (uploading) return;

                    cameraList.querySelectorAll(".dropdown-item").forEach(item => {
                        item.classList.remove("active");
                    });

                    cameraDropdownButton.textContent = "Switching camera…";
                    cameraDropdownButton.disabled = true;

                    const started = await startCamera(camera.deviceId);

                    if (started) {
                        button.classList.add("active");
                        cameraDropdownButton.textContent = button.textContent;
                        localStorage.setItem(defaultCamera, camera.deviceId);
                    }

                    updateActionState();
                });

                li.appendChild(button);
                cameraList.appendChild(li);
            });

            // If the active track did not expose its device ID, show the first label.
            if (!activeId && cameras.length) {
                cameraDropdownButton.textContent =
                    cameras[0].label || "Camera 1";
            }

            cameraDropdownButton.disabled = !cameraReady || uploading;
        } catch (error) {
            console.error("Unable to enumerate cameras:", error);
            setStatus("Camera started, but the camera list could not be loaded.", "warning");
        }
    }

    async function initializeCamera() {
        const token = ++initializationToken;

        if (!navigator.mediaDevices?.getUserMedia) {
            setStatus(
                "Camera access requires a supported browser over HTTPS or localhost.",
                "error"
            );
            updateActionState();
            return;
        }

        const savedDeviceId = localStorage.getItem(defaultCamera);
        let started = false;

        if (savedDeviceId) {
            started = await startCamera(savedDeviceId, true);

            if (!started) {
                localStorage.removeItem(defaultCamera);
            }
        }

        if (!started) {
            started = await startCamera(null, false);
        }

        if (token !== initializationToken) return;

        if (started) {
            await refreshCameraList();
        }
    }

    async function openCameraModal() {
        if (uploading) return;

        setFarmerName();
        currentStep = 0;

        Object.keys(photos).forEach(key => {
            photos[key] = null;
        });

        setCameraReviewMode(false);
        setCapturedPreviewMode(false);

        if (cameraControlPanel) {
            cameraControlPanel.style.removeProperty("display");
        }

        cameraReady = false;
        setStatus("Preparing camera…", "info", true);
        updateStep();

        // Show the modal immediately so the user can see permission/status feedback.
        modal.show();

        await initializeCamera();
        requestAnimationFrame(resizeCameraPreview);
    }

    function captureCurrentPhoto() {
        if (!cameraReady || !video?.videoWidth || !video?.videoHeight) {
            setStatus("The camera is not ready yet.", "warning");
            updateActionState();
            return;
        }

        const ctx = canvas?.getContext("2d");
        if (!ctx || !canvas) {
            setStatus("Unable to prepare the photo canvas.", "error");
            return;
        }

        ctx.imageSmoothingEnabled = true;
        ctx.imageSmoothingQuality = "high";

        const step = steps[currentStep];

        if (step.aspect === "square") {
            const size = Math.min(video.videoWidth, video.videoHeight);
            const sx = (video.videoWidth - size) / 2;
            const sy = (video.videoHeight - size) / 2;

            canvas.width = size;
            canvas.height = size;
            ctx.drawImage(video, sx, sy, size, size, 0, 0, size, size);
        } else {
            const targetRatio = 1.6;
            let cropWidth = video.videoWidth;
            let cropHeight = cropWidth / targetRatio;

            if (cropHeight > video.videoHeight) {
                cropHeight = video.videoHeight;
                cropWidth = cropHeight * targetRatio;
            }

            const sx = (video.videoWidth - cropWidth) / 2;
            const sy = (video.videoHeight - cropHeight) / 2;

            canvas.width = Math.round(cropWidth);
            canvas.height = Math.round(cropHeight);

            ctx.drawImage(
                video,
                sx,
                sy,
                cropWidth,
                cropHeight,
                0,
                0,
                canvas.width,
                canvas.height
            );
        }

        const photo = canvas.toDataURL("image/jpeg", 0.95);

        photos[step.key] = photo;
        image.src = photo;

        cameraContainer.style.display = "none";
        imageContainer.style.display = "block";
        captureBtn.style.display = "none";
        retakeBtn.style.display = "";
        setCapturedPreviewMode(true);

        setStatus("Photo captured. Review it, then continue.", "ready");
        updateActionState();

        requestAnimationFrame(() => {
            resizeCameraPreview();
            syncCameraPreviewDimensions();
        });
    }

    function retakeCurrentPhoto() {
        const step = steps[currentStep];
        photos[step.key] = null;

        setCapturedPreviewMode(false);

        imageContainer.style.display = "none";
        cameraContainer.style.display = "block";
        captureBtn.style.display = "";
        retakeBtn.style.display = "none";

        setStatus(cameraReady ? "Camera ready." : "Preparing camera…", cameraReady ? "ready" : "info", !cameraReady);
        updateActionState();
        requestAnimationFrame(resizeCameraPreview);
    }

    function goNext() {
        if (!photos[steps[currentStep].key]) {
            setStatus("Capture a photo before continuing.", "warning");
            return;
        }

        if (returnToReviewAfterRetake) {
            returnToReviewAfterRetake = false;
            showReview();
            return;
        }

        if (currentStep < steps.length - 1) {
            currentStep++;
            updateStep();
            setStatus(cameraReady ? "Camera ready." : "Preparing camera…", cameraReady ? "ready" : "info", !cameraReady);
        } else {
            showReview();
        }
    }

    function showReview() {
        setCapturedPreviewMode(false);
        setCameraReviewMode(true);

        stopCamera();

        previewContainer.style.display = "none";
        reviewContainer.style.display = "block";

        if (cameraControlPanel) {
            cameraControlPanel.style.setProperty("display", "none", "important");
        }

        reviewBtnContainer.style.display = "block";
        uploadProgressContainer.style.display = "none";

        document.getElementById("reviewFarmer").src = photos.farmer || "";
        document.getElementById("reviewFarmerID").src = photos.farmerWithID || "";
        document.getElementById("reviewFront").src = photos.idFront || "";
        document.getElementById("reviewBack").src = photos.idBack || "";

        const captureTitle = document.getElementById("captureTitle");
        if (captureTitle) captureTitle.textContent = "Review Captured Photos";

        setDisabled(reviewRetakeButton, false);
        setDisabled(reviewSaveButton, false);
    }

    async function retakeSingle(stepIndex) {
        if (uploading) return;

        const parsedIndex = Number(stepIndex);

        if (!Number.isInteger(parsedIndex)
            || parsedIndex < 0
            || parsedIndex >= steps.length) {
            return;
        }

        currentStep = parsedIndex;
        returnToReviewAfterRetake = true;

        photos[steps[currentStep].key] = null;

        setCameraReviewMode(false);
        setCapturedPreviewMode(false);

        reviewContainer.style.display = "none";
        previewContainer.style.display = "block";

        if (cameraControlPanel) {
            cameraControlPanel.style.removeProperty("display");
        }

        cameraReady = false;
        updateStep();
        setStatus("Preparing camera…", "info", true);

        await initializeCamera();

        if (cameraReady) {
            setStatus(
                "Retake " + steps[currentStep].title + ".",
                "ready"
            );
        }
    }

    async function retakeAll() {
        if (uploading) return;

        returnToReviewAfterRetake = false;
        setCameraReviewMode(false);
        setCapturedPreviewMode(false);

        Object.keys(photos).forEach(key => {
            photos[key] = null;
        });

        currentStep = 0;
        reviewContainer.style.display = "none";
        previewContainer.style.display = "block";

        if (cameraControlPanel) {
            cameraControlPanel.style.removeProperty("display");
        }

        cameraReady = false;
        updateStep();
        setStatus("Preparing camera…", "info", true);

        await initializeCamera();
    }

    function resetCameraModal() {
        initializationToken++;
        stopCamera();

        setCameraReviewMode(false);
        setCapturedPreviewMode(false);

        currentStep = 0;
        uploading = false;
        returnToReviewAfterRetake = false;

        Object.keys(photos).forEach(key => {
            photos[key] = null;
        });

        if (image) image.src = "";

        ["reviewFarmer", "reviewFarmerID", "reviewFront", "reviewBack"].forEach(id => {
            const element = document.getElementById(id);
            if (element) element.src = "";
        });

        if (canvas) {
            canvas.width = 0;
            canvas.height = 0;
        }

        if (imageContainer) imageContainer.style.display = "none";
        if (cameraContainer) cameraContainer.style.display = "block";
        if (captureBtn) captureBtn.style.display = "";
        if (retakeBtn) retakeBtn.style.display = "none";
        if (nextBtn) nextBtn.style.display = "";
        if (previewContainer) previewContainer.style.display = "block";
        if (reviewContainer) reviewContainer.style.display = "none";

        if (cameraControlPanel) {
            cameraControlPanel.style.removeProperty("display");
        }

        if (reviewBtnContainer) reviewBtnContainer.style.display = "block";
        if (uploadProgressContainer) uploadProgressContainer.style.display = "none";
        if (closeCaptureModal) closeCaptureModal.disabled = false;

        cameraModalElement.style.removeProperty("--camera-preview-size");
        setStatus("Preparing camera…", "info", true);
        updateStep();
    }

    function getTextOrValue(id) {
        const element = document.getElementById(id);
        if (!element) return "";

        return (
            String(element.textContent || "").trim() ||
            String(element.value || "").trim()
        );
    }

    function imageToBlob(imgElement) {
        return new Promise((resolve, reject) => {
            if (!imgElement?.naturalWidth || !imgElement?.naturalHeight) {
                reject(new Error("A captured image is not ready."));
                return;
            }

            const tempCanvas = document.createElement("canvas");
            tempCanvas.width = imgElement.naturalWidth;
            tempCanvas.height = imgElement.naturalHeight;

            const context = tempCanvas.getContext("2d");
            if (!context) {
                reject(new Error("Unable to prepare image data."));
                return;
            }

            context.drawImage(imgElement, 0, 0);

            tempCanvas.toBlob(blob => {
                if (blob) {
                    resolve(blob);
                } else {
                    reject(new Error("Unable to create image file."));
                }
            }, "image/jpeg", 0.95);
        });
    }

    function showUploadProgress() {
        reviewBtnContainer.style.display = "none";
        uploadProgressContainer.style.display = "block";
        updateUploadProgress(0, 4);
    }

    function hideUploadProgress() {
        reviewBtnContainer.style.display = "block";
        uploadProgressContainer.style.display = "none";
    }

    function updateUploadProgress(done, total) {
        const percent = Math.round((done / total) * 100);
        const bar = document.getElementById("uploadProgressBar");
        const text = document.getElementById("uploadProgressText");

        if (bar) {
            bar.style.width = `${percent}%`;
            bar.textContent = `${percent}%`;
            bar.setAttribute("aria-valuenow", String(percent));
        }

        if (text) {
            text.textContent = `Uploading ${done} of ${total}...`;
        }
    }

    async function uploadAllImages() {
        const basename = getTextOrValue("farmerSysgen");
        if (!basename) {
            throw new Error("Farmer SysGen is missing.");
        }

        const imageSpecs = [
            ["reviewFarmer", `${basename}_Farmer.jpg`],
            ["reviewFarmerID", `${basename}_FarmerwithID.jpg`],
            ["reviewFront", `${basename}_IDFront.jpg`],
            ["reviewBack", `${basename}_IDBack.jpg`]
        ];

        const images = [];

        for (const [id, filename] of imageSpecs) {
            images.push({
                filename,
                blob: await imageToBlob(document.getElementById(id))
            });
        }

        showUploadProgress();

        let uploaded = 0;

        for (const item of images) {
            const form = new FormData();
            const payoutUid =
                new URLSearchParams(window.location.search).get("id") || "";
            const actor =
                typeof getUsername === "function"
                    ? getUsername()
                    : "";

            form.append("uid", payoutUid);
            form.append("actor", actor);
            form.append("folder", basename);
            form.append("file", item.blob, item.filename);

            const response = await fetch("upload.php", {
                method: "POST",
                body: form,
                cache: "no-store"
            });

            if (!response.ok) {
                throw new Error(`Upload failed with HTTP ${response.status}.`);
            }

            let result;
            try {
                result = await response.json();
            } catch {
                throw new Error("Upload server returned an invalid response.");
            }

            if (!result?.success) {
                throw new Error(result?.message || "Upload failed.");
            }

            uploaded++;
            updateUploadProgress(uploaded, images.length);
        }
    }

    async function saveImagesLocally() {
        const basename = getTextOrValue("farmerSysgen") || "PAFFP_Farmer";

        const imageSpecs = [
            ["reviewFarmer", `${basename}_Farmer.jpg`],
            ["reviewFarmerID", `${basename}_FarmerwithID.jpg`],
            ["reviewFront", `${basename}_IDFront.jpg`],
            ["reviewBack", `${basename}_IDBack.jpg`]
        ];

        for (const [id, filename] of imageSpecs) {
            const blob = await imageToBlob(document.getElementById(id));
            const url = URL.createObjectURL(blob);
            const anchor = document.createElement("a");

            anchor.href = url;
            anchor.download = filename;
            anchor.style.display = "none";
            document.body.appendChild(anchor);
            anchor.click();
            anchor.remove();

            window.setTimeout(() => URL.revokeObjectURL(url), 1500);

            // A small pause reduces the chance of browsers dropping successive downloads.
            await new Promise(resolve => window.setTimeout(resolve, 120));
        }
    }

    async function saveReviewPhotos() {
        if (uploading) return;

        setUploadLock(true);
        showUploadProgress();

        let uploadedSuccessfully = false;

        try {
            await uploadAllImages();
            uploadedSuccessfully = true;
        } catch (error) {
            console.error("Photo upload failed:", error);

            hideUploadProgress();

            try {
                await saveImagesLocally();
                if (typeof window.showPaffpNotice === "function") {
                    window.showPaffpNotice({
                        tone: "warning",
                        title: "Upload Unavailable",
                        message:
                            "Unable to upload the photos. The captured images were saved to this device instead."
                    });
                }
            } catch (localError) {
                console.error("Local photo save failed:", localError);
                if (typeof window.showPaffpNotice === "function") {
                    window.showPaffpNotice({
                        tone: "danger",
                        title: "Photo Save Failed",
                        message:
                            "Unable to upload or save the captured photos. Please keep this window open and try again."
                    });
                }
                setUploadLock(false);
                return;
            }
        } finally {
            hideUploadProgress();
            setUploadLock(false);
        }

        modal.hide();

        // Only show the upload-success dialog when the server upload really succeeded.
        if (uploadedSuccessfully && uploadSuccessModal) {
            window.setTimeout(() => {
                uploadSuccessModal.show();
            }, 350);
        }
    }

    document.addEventListener("click", event => {
        if (event.target.closest(".captureModal")) {
            openCameraModal();
        }
    });

    captureButton?.addEventListener("click", captureCurrentPhoto);
    retakeButton?.addEventListener("click", retakeCurrentPhoto);
    nextButton?.addEventListener("click", goNext);
    reviewRetakeButton?.addEventListener("click", retakeAll);

    reviewRetakeOneButtons.forEach(button => {
        button.addEventListener("click", function () {
            retakeSingle(button.dataset.retakeStep);
        });
    });

    reviewSaveButton?.addEventListener("click", saveReviewPhotos);

    image?.addEventListener("load", () => {
        requestAnimationFrame(syncCameraPreviewDimensions);
    });

    cameraModalElement.addEventListener("shown.bs.modal", () => {
        requestAnimationFrame(() => {
            resizeCameraPreview();
            syncCameraPreviewDimensions();
        });
    });

    cameraModalElement.addEventListener("hidden.bs.modal", resetCameraModal);

    window.addEventListener("resize", () => {
        if (cameraModalElement.classList.contains("show")) {
            resizeCameraPreview();
        }
    });

    window.visualViewport?.addEventListener("resize", () => {
        if (cameraModalElement.classList.contains("show")) {
            resizeCameraPreview();
        }
    });

    // Existing success-modal action.
    document.getElementById("openAgriVerifyBtn")?.addEventListener("click", () => {
        const agriverifyWindow = window.open(
            "https://agriverify-rsbsa.da.gov.ph/portal",
            "_blank"
        );

        if (agriverifyWindow) {
            agriverifyWindow.focus();
        } else {
            if (typeof window.showPaffpNotice === "function") {
                window.showPaffpNotice({
                    tone: "warning",
                    title: "Pop-up Blocked",
                    message:
                        "Please allow pop-ups for this site to open AgriVerify."
                });
            }
        }
    });

    resetCameraModal();
})();
