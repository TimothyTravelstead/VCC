/**
 * Persistent Debug Logger for Trainee Logout Issue
 * Captures initialization flow events before logout occurs
 */

class TraineeDebugLogger {
    constructor() {
        this.logKey = 'trainee_debug_logs';
        this.maxLogs = 100;
        this.init();
    }

    init() {
        // Clear old logs on new session
        if (!sessionStorage.getItem('debug_session_active')) {
            localStorage.removeItem(this.logKey);
            sessionStorage.setItem('debug_session_active', 'true');
        }
        
        this.log('LOGGER', 'Debug logger initialized');
        
        // Hook into console methods to capture all logs
        this.hookConsole();
        
        // Hook into window error events
        this.hookErrors();
        
        // Track page visibility to detect logout
        this.trackPageVisibility();
    }

    log(category, message, data = null) {
        const timestamp = new Date().toISOString();
        const logEntry = {
            timestamp,
            category,
            message,
            data: data ? JSON.stringify(data) : null,
            url: window.location.href,
            userAgent: navigator.userAgent.substring(0, 100)
        };

        // Store in localStorage for persistence
        const logs = this.getLogs();
        logs.push(logEntry);
        
        // Keep only recent logs
        if (logs.length > this.maxLogs) {
            logs.splice(0, logs.length - this.maxLogs);
        }
        
        localStorage.setItem(this.logKey, JSON.stringify(logs));
        
        // Also output to console with special prefix
        console.log(`🔍 TRAINEE_DEBUG [${category}]:`, message, data || '');
    }

    getLogs() {
        try {
            return JSON.parse(localStorage.getItem(this.logKey) || '[]');
        } catch (e) {
            return [];
        }
    }

    hookConsole() {
        const originalLog = console.log;
        const originalError = console.error;
        const originalWarn = console.warn;
        
        console.log = (...args) => {
            this.log('CONSOLE_LOG', args.join(' '));
            originalLog.apply(console, args);
        };
        
        console.error = (...args) => {
            this.log('CONSOLE_ERROR', args.join(' '));
            originalError.apply(console, args);
        };
        
        console.warn = (...args) => {
            this.log('CONSOLE_WARN', args.join(' '));
            originalWarn.apply(console, args);
        };
    }

    hookErrors() {
        window.addEventListener('error', (event) => {
            this.log('JS_ERROR', event.message, {
                filename: event.filename,
                lineno: event.lineno,
                colno: event.colno,
                stack: event.error?.stack
            });
        });
        
        window.addEventListener('unhandledrejection', (event) => {
            this.log('PROMISE_REJECTION', event.reason?.message || event.reason, {
                stack: event.reason?.stack
            });
        });
    }

    trackPageVisibility() {
        document.addEventListener('visibilitychange', () => {
            this.log('PAGE_VISIBILITY', `Page visibility: ${document.visibilityState}`);
        });
        
        window.addEventListener('beforeunload', () => {
            this.log('PAGE_UNLOAD', 'Page is about to unload/logout');
        });
    }

    // Method to check specific trainee initialization states
    checkTraineeState() {
        const state = {
            volunteerID: document.getElementById('volunteerID')?.value,
            trainee: document.getElementById('trainee')?.value,
            trainerID: document.getElementById('trainerID')?.value,
            assignedTraineeIDs: document.getElementById('assignedTraineeIDs')?.value,
            localVideo: !!document.getElementById('localVideo'),
            remoteVideo: !!document.getElementById('remoteVideo'),
            trainingSession: !!window.trainingSession,
            simpleTrainingScreenShare: !!window.simpleTrainingScreenShare,
            sessionAuth: typeof window.sessionVars !== 'undefined' ? window.sessionVars : 'undefined'
        };
        
        this.log('TRAINEE_STATE_CHECK', 'Current trainee state', state);
        return state;
    }

    // Method to display logs in console for debugging
    displayLogs() {
        const logs = this.getLogs();
        console.group('🔍 TRAINEE DEBUG LOGS');
        logs.forEach((log, index) => {
            console.log(`${index + 1}. [${log.timestamp}] ${log.category}: ${log.message}`, 
                       log.data ? JSON.parse(log.data) : '');
        });
        console.groupEnd();
        return logs;
    }

    // Clear all logs
    clearLogs() {
        localStorage.removeItem(this.logKey);
        this.log('LOGGER', 'Logs cleared');
    }

    // Export logs as text for sharing
    exportLogs() {
        const logs = this.getLogs();
        const text = logs.map(log => 
            `${log.timestamp} [${log.category}] ${log.message}${log.data ? ' | Data: ' + log.data : ''}`
        ).join('\n');
        
        return text;
    }
}

// Create global debug logger instance
window.traineeDebugLogger = new TraineeDebugLogger();

// Add helper methods to window for easy console access
window.debugTrainee = {
    checkState: () => window.traineeDebugLogger.checkTraineeState(),
    showLogs: () => window.traineeDebugLogger.displayLogs(),
    clearLogs: () => window.traineeDebugLogger.clearLogs(),
    exportLogs: () => window.traineeDebugLogger.exportLogs(),
    log: (message, data) => window.traineeDebugLogger.log('MANUAL', message, data)
};

// Initial state check
window.traineeDebugLogger.checkTraineeState();
window.traineeDebugLogger.log('INITIALIZATION', 'Trainee debug logger ready');

console.log('🔍 Trainee Debug Logger loaded. Use debugTrainee.showLogs() to view captured logs.');