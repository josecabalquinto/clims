import '../css/scanner.css';

function equipmentScanner() {
    let scanner = null;
    let generation = 0;
    let onNavigate;
    let onVisibility;
    let lastCameraCode = '';
    let lastCameraCodeSeenAt = 0;

    return {
        isOpen: false, mode: 'camera', running: false, starting: false,
        busy: false, error: '', result: null, code: '', cameras: [], cameraId: '',

        init() {
            onNavigate = () => this.closed();
            onVisibility = () => { if (document.hidden) this.stopCamera(); };
            document.addEventListener('livewire:navigating', onNavigate);
            document.addEventListener('visibilitychange', onVisibility);
        },
        destroy() {
            this.closed();
            document.removeEventListener('livewire:navigating', onNavigate);
            document.removeEventListener('visibilitychange', onVisibility);
        },
        open() {
            this.stopCamera();
            this.isOpen = true;
            this.error = '';
            this.result = null;
            this.code = '';
            this.$root.querySelector('dialog[data-modal="equipment-scanner"]')?.setAttribute('aria-labelledby', 'equipment-scan-heading');
            this.$flux.modal('equipment-scanner').show();
            if (this.mode === 'entry') this.$nextTick(() => this.$refs.codeInput.focus());
        },
        closed() {
            this.isOpen = false;
            this.stopCamera();
        },
        setMode(mode) {
            this.stopCamera();
            this.mode = mode;
            this.error = '';
            this.result = null;
            this.code = '';
            if (mode === 'entry') this.$nextTick(() => this.$refs.codeInput.focus());
        },
        async startCamera() {
            if (this.starting || this.busy || !this.isOpen) return;
            this.error = '';
            this.result = null;
            if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
                this.error = 'Camera scanning needs HTTPS or localhost. You can still use a USB scanner, enter an asset tag, or select equipment manually.';
                return;
            }
            this.stopCamera();
            const run = generation;
            this.starting = true;
            let current;
            try {
                // Keep the video rendered before asking the scanner to start.
                // Mobile Safari and some Android WebViews can return a live stream
                // but paint a black frame when playback begins on display: none.
                await this.$nextTick();
                const { default: QrScanner } = await import('qr-scanner');
                if (run !== generation || !this.isOpen) return;
                current = new QrScanner(this.$refs.video, (scan) => {
                    const decodedCode = String(scan.data);
                    if (decodedCode === lastCameraCode) {
                        lastCameraCodeSeenAt = Date.now();
                        return;
                    }
                    lastCameraCode = decodedCode;
                    lastCameraCodeSeenAt = Date.now();
                    if (!this.busy && !this.result && this.isOpen) this.submit(decodedCode);
                }, {
                    preferredCamera: this.cameraId || 'environment',
                    maxScansPerSecond: 6,
                    returnDetailedScanResult: true,
                    onDecodeError: () => {
                        if (lastCameraCode && Date.now() - lastCameraCodeSeenAt > 500) lastCameraCode = '';
                    },
                });
                scanner = current;
                await current.start();
                if (run !== generation || !this.isOpen) { current.destroy(); return; }
                await this.$refs.video.play();
                if (run !== generation || !this.isOpen) { current.destroy(); return; }
                this.running = true;
                this.cameras = await QrScanner.listCameras();
                this.cameraId = this.$refs.video.srcObject?.getVideoTracks()[0]?.getSettings().deviceId || '';
            } catch (error) {
                if (run !== generation || !this.isOpen) return;
                this.stopCamera();
                const name = error?.name || '';
                this.error = name === 'NotAllowedError' || name === 'SecurityError'
                    ? 'Camera permission was denied. Allow camera access in your browser, or use the scanner / asset tag option.'
                    : 'The camera is unavailable. Allow camera access, check that it is connected and not in use, or use the scanner / asset tag option.';
            } finally {
                if (run === generation) this.starting = false;
            }
        },
        stopCamera() {
            generation++;
            scanner?.destroy();
            scanner = null;
            const video = this.$refs.video;
            video?.srcObject?.getTracks().forEach((track) => track.stop());
            if (video) {
                video.pause();
                video.srcObject = null;
                video.removeAttribute('src');
                video.load();
            }
            this.running = false;
            this.starting = false;
        },
        async switchCamera() {
            if (!scanner || this.cameras.length < 2) return;
            const index = this.cameras.findIndex((camera) => camera.id === this.cameraId);
            const next = this.cameras[(index + 1) % this.cameras.length];
            try {
                await scanner.setCamera(next.id);
                this.cameraId = next.id;
            } catch {
                this.error = 'This camera could not be opened. Try another camera or use an asset tag.';
                this.stopCamera();
            }
        },
        async submit(value) {
            if (this.busy || !this.isOpen) return;
            this.stopCamera();
            this.busy = true;
            this.error = '';
            this.result = null;
            try {
                const result = await this.$wire.addScannedEquipment(String(value).trim());
                if (this.isOpen) {
                    this.result = result;
                    this.code = '';
                    if (this.mode === 'entry') this.$nextTick(() => this.$refs.codeInput.focus());
                }
            } catch {
                if (this.isOpen) this.error = 'Equipment could not be checked. Try again, or close scanning and continue manually.';
            } finally {
                this.busy = false;
            }
        },
        scanAnother() {
            this.result = null;
            this.error = '';
            this.code = '';
            if (this.mode === 'camera') this.startCamera();
            else this.$nextTick(() => this.$refs.codeInput.focus());
        },
    };
}

function equipmentQrLabel(data) {
    return {
        image: '', error: '', loading: false,
        async open() {
            this.$root.querySelector('dialog[data-modal="equipment-qr-label"]')?.setAttribute('aria-labelledby', 'qr-label-heading');
            this.$flux.modal('equipment-qr-label').show();
            if (this.image || this.loading) return;
            this.loading = true;
            this.error = '';
            try {
                const { default: QRCode } = await import('qrcode');
                this.image = await QRCode.toDataURL(data.payload, { width: 720, margin: 4, errorCorrectionLevel: 'M', color: { dark: '#000000', light: '#ffffff' } });
            } catch { this.error = 'The QR label could not be generated. Close the dialog and try again.'; }
            finally { this.loading = false; }
        },
        download() {
            if (!this.image) return;
            const link = document.createElement('a');
            link.href = this.image;
            link.download = (data.assetTag.replace(/[^a-z0-9_-]/gi, '_') || 'equipment') + '-qr.png';
            link.click();
        },
        print() {
            if (!this.image) return;
            const target = window.open('', '_blank', 'width=480,height=640');
            if (!target) { this.error = 'Allow popups to print the label, or download the QR instead.'; return; }
            const doc = target.document;
            doc.title = 'Equipment QR label';
            const style = doc.createElement('style');
            style.textContent = '@page{margin:12mm}body{font-family:Arial,sans-serif;color:#000;background:#fff;text-align:center;margin:24px}article{display:inline-block;width:260px;border:1px solid #ddd;padding:16px;overflow-wrap:anywhere}h1{font-size:16px;margin:0 0 8px}p{font-size:13px;margin:8px 0}img{width:240px;height:240px;display:block;margin:auto}strong{font-size:18px}';
            doc.head.append(style);
            const label = doc.createElement('article');
            const heading = doc.createElement('h1'); heading.textContent = 'CLIMS equipment';
            const name = doc.createElement('p'); name.textContent = data.name;
            const image = doc.createElement('img'); image.alt = 'Equipment QR code';
            const tag = doc.createElement('strong'); tag.textContent = data.assetTag;
            label.append(heading, name, image, tag); doc.body.append(label);
            target.addEventListener('afterprint', () => target.close(), { once: true });
            image.onload = () => { target.focus(); target.print(); };
            image.src = this.image;
        },
    };
}

function register() {
    window.Alpine.data('equipmentScanner', equipmentScanner);
    window.Alpine.data('equipmentQrLabel', equipmentQrLabel);
}
document.addEventListener('alpine:init', register, { once: true });
if (window.Alpine) register();
