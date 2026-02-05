class AjaxRequest {
    constructor(url, params = null, resultsFunction = null, resultsObject = null, method = "POST") {
        this.url = url;
        this.type = method.toUpperCase(); // Accept method parameter
        this.contentType = "application/x-www-form-urlencoded";
        this.results = resultsFunction;
        this.resultsObject = resultsObject;
        this.abortController = new AbortController();
        
        // Handle params if provided
        if (params) {
            if (this.type === "GET") {
                // For GET requests, append params to URL
                const paramString = this.processParams(params);
                this.url += (this.url.includes('?') ? '&' : '?') + paramString;
            } else {
                // For POST requests, keep params in body
                this.params = this.processParams(params);
            }
        }
        // Start the request process
        this.process();
    }

    processParams(params) {
        if (typeof params === 'object' && params !== null) {
            // Convert object to URL-encoded query string
            return this.encodeParams(params);
        } else if (typeof params === 'string') {
            // Parameters are already a URL-encoded query string
            return params;
        } else {
            throw new Error("Invalid params type. Must be either a string or an object.");
        }
    }

    encodeParams(params) {
        // Convert an object to a URL-encoded query string
        return Object.entries(params)
            .map(([key, value]) => 
                `${encodeURIComponent(key)}=${encodeURIComponent(value)}`
            ).join('&');
    }

    // Maintain compatibility with old abort method
    abort() {
        if (this.abortController) {
            this.abortController.abort();
            return "Aborted";
        } else {
            return false;
        }
    }

    // Add method to change request type (GET, POST, etc.)
    setRequestType(type) {
        this.type = type.toUpperCase();
        return this;
    }

    // Add method to change content type
    setContentType(contentType) {
        this.contentType = contentType;
        return this;
    }

    // Set a custom error handler
    setErrorHandler(handlerFunction) {
        this.errorHandler = handlerFunction;
        return this;
    }

    async process() {
        try {
            // Prepare fetch options
            const options = {
                method: this.type,
                headers: {
                    'Content-Type': this.contentType
                },
                signal: this.abortController.signal
            };
            
            // Only add body for POST requests with params
            if (this.params && this.type !== "GET") {
                options.body = this.params;
            }
            
            // Add a timeout wrapper to detect hanging requests
            const timeoutPromise = new Promise((_, reject) => {
                setTimeout(() => reject(new Error('Request timeout after 30 seconds')), 30000);
            });
            
            const fetchPromise = fetch(this.url, options);
            const response = await Promise.race([fetchPromise, timeoutPromise]);
            
            const contentType = response.headers.get("content-type");
            const isJson = contentType && contentType.includes("application/json");
            
            // Try to parse as JSON first if content type is JSON
            let responseData;
            let responseText;
            
            if (isJson) {
                responseData = await response.json();
                // Convert back to string for backward compatibility
                responseText = JSON.stringify(responseData);
            } else {
                responseText = await response.text();
                // Try to parse as JSON anyway (some servers don't set content-type correctly)
                try {
                    responseData = JSON.parse(responseText);
                } catch (e) {
                    // Not JSON, use as text
                    responseData = responseText;
                }
            }
            
            // For error responses, handle them appropriately
            if (!response.ok) {
                // If we have structured error data, use it
                if (typeof responseData === 'object' && responseData !== null) {
                    throw {
                        status: response.status,
                        statusText: response.statusText,
                        data: responseData,
                        message: responseData.message || 'Server error',
                        details: responseData.details || null,
                        code: responseData.code || response.status
                    };
                } else {
                    // Fall back to basic error
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
            }
            
            // Log successful request for debugging (can be disabled in production)
            if (window.AJAX_DEBUG_MODE) {
                console.group('✅ AJAX REQUEST SUCCESS');
                console.log('URL:', this.url);
                console.log('Method:', this.type);
                console.log('Status:', response.status, response.statusText);
                console.log('Content-Type:', contentType);
                console.log('Response Data:', responseData);
                console.groupEnd();
            }

            // Only call results function if provided (backward compatibility)
            if (typeof this.results === 'function') {
                // Pass both the raw text (for backward compatibility) and parsed data
                this.results(responseText, this.resultsObject, responseData);
            }

            return responseData; // Return parsed data for promise chaining
        } catch (e) {
            // Handle fetch abort
            if (e.name === 'AbortError') {
                console.log('Request aborted');
                return null;
            }

            // COMPREHENSIVE ERROR LOGGING
            console.group('🚨 AJAX REQUEST ERROR - DETAILED DIAGNOSTICS');
            console.error('Error Object:', e);
            console.log('Error Name:', e.name);
            console.log('Error Message:', e.message);
            console.log('Stack Trace:', e.stack);
            console.log('Timestamp:', new Date().toISOString());
            console.log('Request URL:', this.url);
            console.log('Request Method:', this.type);
            console.log('Request Params:', this.params || 'none');
            console.log('Content Type:', this.contentType);
            console.log('Results Object:', this.resultsObject);

            // Log additional error properties if they exist
            if (e.data) console.log('Error Data:', e.data);
            if (e.code) console.log('Error Code:', e.code);
            if (e.details) console.log('Error Details:', e.details);

            console.groupEnd();

            // Prepare comprehensive error message for popup
            let detailedMessage = '═══════════════════════════════════════\n';
            detailedMessage += '      AJAX REQUEST ERROR - DIAGNOSTICS\n';
            detailedMessage += '═══════════════════════════════════════\n\n';

            detailedMessage += '🔴 ERROR:\n';
            detailedMessage += `   ${e.message || 'Unknown error'}\n\n`;

            detailedMessage += '📍 REQUEST DETAILS:\n';
            detailedMessage += `   URL: ${this.url}\n`;
            detailedMessage += `   Method: ${this.type}\n`;
            detailedMessage += `   Params: ${this.params || 'none'}\n\n`;

            detailedMessage += '⏰ TIMESTAMP:\n';
            detailedMessage += `   ${new Date().toLocaleString()}\n\n`;

            detailedMessage += '🔧 ERROR TYPE:\n';
            detailedMessage += `   ${e.name}\n\n`;

            if (e.stack) {
                detailedMessage += '📋 STACK TRACE:\n';
                const stackLines = e.stack.split('\n').slice(0, 8); // First 8 lines
                stackLines.forEach(line => {
                    detailedMessage += `   ${line.trim()}\n`;
                });
                detailedMessage += '\n';
            }

            if (e.data || e.code || e.details) {
                detailedMessage += '📦 ADDITIONAL INFO:\n';
                if (e.code) detailedMessage += `   Code: ${e.code}\n`;
                if (e.details) detailedMessage += `   Details: ${e.details}\n`;
                if (e.data) detailedMessage += `   Data: ${JSON.stringify(e.data, null, 2)}\n`;
                detailedMessage += '\n';
            }

            detailedMessage += '═══════════════════════════════════════\n';
            detailedMessage += '💡 TIP: Check browser console for more details\n';
            detailedMessage += '═══════════════════════════════════════';

            // Show error to user
            this.handleError(detailedMessage, e);

            // Re-throw for error handling upstream
            throw e;
        }
    }

    // New method to handle errors - default implementation shows alert (like old system)
    // Can be overridden by subclasses or instance modifications
    handleError(message, error) {
        // If a custom error handler is defined, use it
        if (typeof this.errorHandler === 'function') {
            this.errorHandler(message, error, this.resultsObject);
        } else {
            // Default behavior - show detailed error modal
            // Clear resourceList if it exists (matching old system behavior)
            const resourceList = document.getElementById("resourceList");
            if (resourceList) {
                resourceList.innerHTML = "";
            }

            // Create and show detailed error modal
            this.showDetailedErrorModal(message, error);
        }
    }

    // Create a detailed error modal with copy functionality
    showDetailedErrorModal(message, error) {
        // Remove any existing error modal
        const existingModal = document.getElementById('ajax-error-modal');
        if (existingModal) {
            existingModal.remove();
        }

        // Create modal container
        const modal = document.createElement('div');
        modal.id = 'ajax-error-modal';
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 100000;
            font-family: monospace;
        `;

        // Create modal content
        const content = document.createElement('div');
        content.style.cssText = `
            background: white;
            padding: 20px;
            border-radius: 8px;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
        `;

        // Create header
        const header = document.createElement('div');
        header.style.cssText = `
            background: #dc3545;
            color: white;
            padding: 15px;
            margin: -20px -20px 20px -20px;
            border-radius: 8px 8px 0 0;
            font-size: 18px;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
        `;
        header.innerHTML = `
            <span>🚨 AJAX Request Error</span>
            <button id="ajax-error-close" style="
                background: rgba(255,255,255,0.2);
                border: 1px solid white;
                color: white;
                padding: 5px 15px;
                cursor: pointer;
                border-radius: 4px;
                font-size: 14px;
            ">✕ Close</button>
        `;

        // Create message area
        const messageArea = document.createElement('pre');
        messageArea.style.cssText = `
            white-space: pre-wrap;
            word-wrap: break-word;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid #dc3545;
            font-size: 12px;
            line-height: 1.6;
            max-height: 400px;
            overflow-y: auto;
            margin-bottom: 15px;
        `;
        messageArea.textContent = message;

        // Create button container
        const buttonContainer = document.createElement('div');
        buttonContainer.style.cssText = `
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        `;

        // Create copy button
        const copyButton = document.createElement('button');
        copyButton.textContent = '📋 Copy to Clipboard';
        copyButton.style.cssText = `
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        `;
        copyButton.onclick = () => {
            navigator.clipboard.writeText(message).then(() => {
                copyButton.textContent = '✅ Copied!';
                setTimeout(() => {
                    copyButton.textContent = '📋 Copy to Clipboard';
                }, 2000);
            });
        };

        // Create OK button
        const okButton = document.createElement('button');
        okButton.textContent = 'OK';
        okButton.style.cssText = `
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 30px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        `;
        okButton.onclick = () => {
            modal.remove();
        };

        // Assemble modal
        buttonContainer.appendChild(copyButton);
        buttonContainer.appendChild(okButton);
        content.appendChild(header);
        content.appendChild(messageArea);
        content.appendChild(buttonContainer);
        modal.appendChild(content);

        // Add close button handler
        const closeButton = header.querySelector('#ajax-error-close');
        closeButton.onclick = () => {
            modal.remove();
        };

        // Close on backdrop click
        modal.onclick = (e) => {
            if (e.target === modal) {
                modal.remove();
            }
        };

        // Add to document
        document.body.appendChild(modal);

        // Focus OK button for keyboard accessibility
        okButton.focus();

        // Send error email with console logs
        this.sendErrorEmail(message, error);
    }

    // Function to send error email with console logs
    sendErrorEmail(message, error) {
        // Get console logs
        let consoleLogs = 'Console logging not available';
        if (typeof window.getConsoleLogs === 'function') {
            consoleLogs = window.getConsoleLogs(100); // Get last 100 console entries
        }

        // Extract call SID from params if available
        let callSid = 'Unknown';
        if (this.params) {
            const match = this.params.match(/callSid=([^&]+)/);
            if (match) callSid = decodeURIComponent(match[1]);
        }

        // Prepare data to send
        const errorData = {
            title: 'AJAX Request Error',
            errorMessage: error.message || 'Unknown error',
            errorDetails: message || '',
            volunteer: (typeof VolunteerID !== 'undefined' ? VolunteerID : 'Unknown'),
            callSid: callSid,
            requestData: this.params || '{}',
            consoleLogs: consoleLogs,
            timestamp: new Date().toLocaleString(),
            url: this.url,
            stackTrace: (error && error.stack) ? error.stack : ''
        };

        // Send to error email endpoint
        fetch('sendErrorEmail.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(errorData)
        }).then(response => {
            return response.json();
        }).then(data => {
            if (data.status === 'success') {
                console.log('✅ Error email sent to Tim@LGBTHotline.org');
            } else {
                console.error('❌ Failed to send error email:', data.message);
            }
        }).catch(err => {
            console.error('❌ Error sending email:', err);
        });
    }
}

// Specialized AjaxRequest with retry mechanism and silent failure for background tasks
class BackgroundAjaxRequest extends AjaxRequest {
    constructor(url, params = null, resultsFunction = null, resultsObject = null, options = {}) {
        // Add cache buster to URL
        const cacheBuster = `_cb=${Date.now()}_${Math.floor(Math.random() * 1000000)}`;
        const urlWithCache = url + (url.includes('?') ? '&' : '?') + cacheBuster;

        // Temporarily override process to prevent parent constructor from calling it
        const originalProcess = AjaxRequest.prototype.process;
        AjaxRequest.prototype.process = function() {};

        // Call parent constructor first (required for ES6 class inheritance)
        super(urlWithCache, params, resultsFunction, resultsObject);

        // Restore original process method
        AjaxRequest.prototype.process = originalProcess;

        // Initialize retry options with defaults
        this.maxRetries = options.maxRetries || 3;
        this.initialDelay = options.initialDelay || 1000; // 1 second initial delay
        this.maxDelay = options.maxDelay || 8000; // 8 second max delay
        this.silentMode = options.silentMode !== false; // Default to silent
        this.logErrors = options.logErrors !== false; // Default to log errors
        this.currentAttempt = 0;

        // Now start the process with retry
        this.processWithRetry();
    }

    async processWithRetry() {
        this.currentAttempt = 0;
        
        while (this.currentAttempt <= this.maxRetries) {
            this.currentAttempt++;
            
            try {
                if (this.logErrors && this.currentAttempt > 1) {
                    console.log(`🔄 Background request retry attempt ${this.currentAttempt}/${this.maxRetries + 1} for ${this.url}`);
                }
                
                // Create a new AbortController for each attempt
                this.abortController = new AbortController();
                
                // Call the parent process method
                const result = await super.process();
                
                if (this.logErrors && this.currentAttempt > 1) {
                    console.log(`✅ Background request succeeded on attempt ${this.currentAttempt} for ${this.url}`);
                }
                
                return result;
                
            } catch (error) {
                const isLastAttempt = this.currentAttempt > this.maxRetries;
                
                if (this.logErrors) {
                    if (isLastAttempt) {
                        console.error(`❌ Background request failed permanently after ${this.currentAttempt} attempts for ${this.url}:`, error.message || error);
                    } else {
                        console.warn(`⚠️ Background request attempt ${this.currentAttempt} failed for ${this.url}: ${error.message || error}. Retrying...`);
                    }
                }
                
                if (isLastAttempt) {
                    // Final failure - handle according to silent mode
                    if (!this.silentMode) {
                        // Only show alerts if not in silent mode
                        throw error;
                    } else {
                        // Silent mode - log but don't throw
                        if (this.logErrors) {
                            console.error(`🔇 Silent background request failure for ${this.url} - continuing without error`);
                        }
                        return null;
                    }
                }
                
                // Calculate exponential backoff delay with jitter
                const baseDelay = Math.min(this.initialDelay * Math.pow(2, this.currentAttempt - 1), this.maxDelay);
                const jitter = Math.random() * 0.5 * baseDelay; // Up to 50% jitter
                const delay = baseDelay + jitter;
                
                if (this.logErrors) {
                    console.log(`⏳ Waiting ${Math.round(delay)}ms before retry attempt ${this.currentAttempt + 1}`);
                }
                
                // Wait before retrying
                await new Promise(resolve => setTimeout(resolve, delay));
            }
        }
    }
    
    // Override handleError to respect silent mode
    handleError(message, error) {
        if (!this.silentMode) {
            // Only show alerts if not in silent mode
            super.handleError(message, error);
        } else if (this.logErrors) {
            // In silent mode, just log the error
            console.error(`🔇 Silent background error: ${message}`, error);
        }
    }
}