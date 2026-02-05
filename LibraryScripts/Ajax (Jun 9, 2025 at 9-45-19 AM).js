class AjaxRequest {
    constructor(url, params = null, resultsFunction = null, resultsObject = null) {
        this.url = url;
        this.type = "POST"; // Default method
        this.contentType = "application/x-www-form-urlencoded";
        this.results = resultsFunction;
        this.resultsObject = resultsObject;
        // Handle params if provided
        if (params) {
            this.params = this.processParams(params);
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

    async process() {
        try {
            // Prepare fetch options
            const options = {
                method: this.type,
                headers: {
                    'Content-Type': this.contentType
                }
            };
            // Only add body if params exist
            if (this.params) {
                options.body = this.params;
            }
            
            const response = await fetch(this.url, options);
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
            
            // Only call results function if provided
            if (typeof this.results === 'function') {
                // Pass both the raw text (for backward compatibility) and parsed data
                this.results(responseText, this.resultsObject, responseData);
            }
            
            return responseData; // Return parsed data for promise chaining
        } catch (e) {
            console.error("Error connecting to server:", e);
            
            // Prepare a user-friendly error message
            let errorMessage;
            
            if (e.data && e.message) {
                // This is our structured error
                errorMessage = `Error ${e.code || ''}: ${e.message}`;
                
                // Add details if available
                if (e.details) {
                    errorMessage += `\n\nDetails: ${e.details}`;
                }
            } else {
                // Generic error
                errorMessage = `Unable to connect to server: ${this.url}${this.params ? '\n\n' + this.params : ''}`;
            }
            
            // Show error to user
            this.handleError(errorMessage, e);
            
            // Re-throw for error handling upstream
            throw e;
        }
    }

    // New method to handle errors - default implementation shows alert
    // Can be overridden by subclasses or instance modifications
    handleError(message, error) {
        // If a custom error handler is defined, use it
        if (typeof this.errorHandler === 'function') {
            this.errorHandler(message, error, this.resultsObject);
        } else {
            // Default behavior - show alert
            alert(message);
        }
    }

    // Set a custom error handler
    setErrorHandler(handlerFunction) {
        this.errorHandler = handlerFunction;
        return this;
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
}