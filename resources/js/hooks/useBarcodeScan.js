import { useEffect, useRef, useState, useCallback } from 'react';

/**
 * Custom hook for barcode scanning using keyboard input
 * Handles USB barcode scanners that emulate keyboard input
 * 
 * @param {Object} options
 * @param {Function} options.onScan - Callback when barcode is scanned
 * @param {boolean} options.enabled - Enable/disable scanning
 * @param {number} options.timeout - Time to wait before clearing buffer (ms)
 * @param {number} options.minLength - Minimum barcode length
 * 
 * @returns {Object} { buffer, isScanning }
 */
export function useBarcodeScan({
    onScan,
    enabled = true,
    timeout = 100,
    minLength = 3,
} = {}) {
    const timeoutRef = useRef(null);
    const bufferRef = useRef('');
    const lastKeypressRef = useRef(0);
    const onScanRef = useRef(onScan);
    const [buffer, setBuffer] = useState('');
    const [isScanning, setIsScanning] = useState(false);

    useEffect(() => {
        onScanRef.current = onScan;
    }, [onScan]);

    const clearBuffer = useCallback(() => {
        bufferRef.current = '';
        setBuffer('');
        setIsScanning(false);
    }, []);

    const processBarcode = useCallback(
        (code) => {
            if (code.length >= minLength && onScanRef.current) {
                onScanRef.current(code.trim());
            }
            clearBuffer();
        },
        [minLength, clearBuffer]
    );

    useEffect(() => {
        console.log('🔌 useBarcodeScan useEffect', { enabled });
        
        if (!enabled) {
            clearBuffer();
            return;
        }

        console.log('✅ useBarcodeScan AKTYWNY - listener dodany');

        const handleKeyDown = (event) => {
            console.log('⌨️ KEY DOWN:', event.key, 'target:', event.target.tagName);
            
            // Ignore if user is typing in input/textarea/select (except our scanner input)
            const target = event.target;
            const isInputField =
                target.tagName === 'INPUT' ||
                target.tagName === 'TEXTAREA' ||
                target.tagName === 'SELECT' ||
                target.isContentEditable;

            // Allow scanning only when:
            // 1. Not in any input field OR
            // 2. In our scanner input (data-scanner-input="true")
            if (isInputField && !target.dataset.scannerInput) {
                return;
            }

            const now = Date.now();
            const timeSinceLastKeypress = now - lastKeypressRef.current;

            // If too much time has passed, start new scan
            if (timeSinceLastKeypress > timeout) {
                bufferRef.current = '';
                setBuffer('');
            }

            lastKeypressRef.current = now;

            // Clear existing timeout
            if (timeoutRef.current) {
                clearTimeout(timeoutRef.current);
                timeoutRef.current = null;
            }

            // Check for Enter key (scanner sends Enter at the end)
            if (event.key === 'Enter' && bufferRef.current.length > 0) {
                console.log('🎯 ENTER naciśnięty! Buffer:', bufferRef.current);
                event.preventDefault();
                setIsScanning(false);
                processBarcode(bufferRef.current);
                return;
            }

            // Ignore special keys (except Enter which we handled above)
            if (event.key.length > 1) {
                console.log('⏭️ Ignoruję klawisz specjalny:', event.key);
                return;
            }

            // Add character to buffer
            bufferRef.current += event.key;
            console.log('📝 Buffer zaktualizowany:', bufferRef.current);
            setBuffer(bufferRef.current);
            setIsScanning(true);

            // Set timeout to process barcode if Enter is not received
            // This handles scanners that don't send Enter
            timeoutRef.current = setTimeout(() => {
                console.log('⏰ Timeout! Przetwarzam buffer:', bufferRef.current);
                if (bufferRef.current.length >= minLength) {
                    setIsScanning(false);
                    processBarcode(bufferRef.current);
                } else {
                    clearBuffer();
                }
                timeoutRef.current = null;
            }, timeout);
        };

        window.addEventListener('keydown', handleKeyDown);

        return () => {
            window.removeEventListener('keydown', handleKeyDown);
            if (timeoutRef.current) {
                clearTimeout(timeoutRef.current);
                timeoutRef.current = null;
            }
        };
    }, [enabled, timeout, minLength, processBarcode, clearBuffer]);

    return { buffer, isScanning, clearBuffer };
}
