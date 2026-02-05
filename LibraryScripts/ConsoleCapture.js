// ═══════════════════════════════════════════════════════════════
// CONSOLE LOG CAPTURE SYSTEM
// Captures console output for error reporting
// ═══════════════════════════════════════════════════════════════

(function() {
    return;
    // Create a circular buffer for console logs (keep last 100 entries)
    window.consoleLogBuffer = [];
    const MAX_LOG_ENTRIES = 100;

    // Helper to safely stringify objects
    function safeStringify(obj, maxDepth = 3, currentDepth = 0) {
        if (currentDepth > maxDepth) return '[Max Depth Reached]';

        try {
            if (obj === null) return 'null';
            if (obj === undefined) return 'undefined';
            if (typeof obj === 'string') return obj;
            if (typeof obj === 'number') return obj.toString();
            if (typeof obj === 'boolean') return obj.toString();
            if (typeof obj === 'function') return '[Function]';
            if (obj instanceof Error) return obj.stack || obj.message;
            if (Array.isArray(obj)) {
                return '[' + obj.map(item => safeStringify(item, maxDepth, currentDepth + 1)).join(', ') + ']';
            }
            if (typeof obj === 'object') {
                const keys = Object.keys(obj).slice(0, 20); // Limit to first 20 keys
                const pairs = keys.map(key => {
                    try {
                        return key + ': ' + safeStringify(obj[key], maxDepth, currentDepth + 1);
                    } catch (e) {
                        return key + ': [Error stringifying]';
                    }
                });
                return '{' + pairs.join(', ') + (Object.keys(obj).length > 20 ? ', ...' : '') + '}';
            }
            return String(obj);
        } catch (e) {
            return '[Error: ' + e.message + ']';
        }
    }

    // Add entry to buffer
    function addToBuffer(type, args) {
        const timestamp = new Date().toISOString();
        const message = Array.from(args).map(arg => safeStringify(arg)).join(' ');

        window.consoleLogBuffer.push({
            timestamp: timestamp,
            type: type,
            message: message
        });

        // Keep buffer size manageable
        if (window.consoleLogBuffer.length > MAX_LOG_ENTRIES) {
            window.consoleLogBuffer.shift();
        }
    }

    // Save original console methods
    const originalLog = console.log;
    const originalError = console.error;
    const originalWarn = console.warn;
    const originalInfo = console.info;
    const originalDebug = console.debug;

    // Wrap console.log
    console.log = function() {
        addToBuffer('LOG', arguments);
        originalLog.apply(console, arguments);
    };

    // Wrap console.error
    console.error = function() {
        addToBuffer('ERROR', arguments);
        originalError.apply(console, arguments);
    };

    // Wrap console.warn
    console.warn = function() {
        addToBuffer('WARN', arguments);
        originalWarn.apply(console, arguments);
    };

    // Wrap console.info
    console.info = function() {
        addToBuffer('INFO', arguments);
        originalInfo.apply(console, arguments);
    };

    // Wrap console.debug
    console.debug = function() {
        addToBuffer('DEBUG', arguments);
        originalDebug.apply(console, arguments);
    };

    // Capture uncaught errors
    window.addEventListener('error', function(event) {
        addToBuffer('UNCAUGHT ERROR', [event.message, 'at', event.filename + ':' + event.lineno + ':' + event.colno]);
    });

    // Capture unhandled promise rejections
    window.addEventListener('unhandledrejection', function(event) {
        addToBuffer('UNHANDLED REJECTION', [event.reason]);
    });

    // Function to get console logs as formatted string
    window.getConsoleLogs = function(lastN = 50) {
        const logs = window.consoleLogBuffer.slice(-lastN);
        if (logs.length === 0) {
            return 'No console logs captured';
        }

        let formatted = '═══════════════════════════════════════\n';
        formatted += '      CONSOLE LOGS (Last ' + logs.length + ' entries)\n';
        formatted += '═══════════════════════════════════════\n\n';

        logs.forEach(function(log) {
            const time = new Date(log.timestamp).toLocaleTimeString();
            formatted += '[' + time + '] [' + log.type + '] ' + log.message + '\n';
        });

        return formatted;
    };

    // Function to clear console log buffer
    window.clearConsoleLogs = function() {
        window.consoleLogBuffer = [];
    };

    console.log('✅ Console capture initialized - logging to buffer');
})();
