import QRCode from 'qrcode';

window.CFOCreateQr = (canvas, text) => QRCode.toCanvas(canvas, text, {
    width: 240,
    margin: 2,
    errorCorrectionLevel: 'M',
    color: { dark: '#073d33', light: '#ffffff' },
});

window.dispatchEvent(new Event('cfo:qr-ready'));
