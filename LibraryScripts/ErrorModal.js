// ═══════════════════════════════════════════════════════════════
// COMPREHENSIVE ERROR MODAL SYSTEM
// Provides detailed error dialogs with copy-to-clipboard functionality
// ═══════════════════════════════════════════════════════════════

function showComprehensiveError(title, message, errorContext) {
	// Build comprehensive error message
	var errorDetails = '═══════════════════════════════════════\n';
	errorDetails += '      ' + title.toUpperCase() + '\n';
	errorDetails += '═══════════════════════════════════════\n\n';

	errorDetails += '🔴 ERROR:\n';
	errorDetails += '   ' + message + '\n\n';

	errorDetails += '⏰ TIMESTAMP:\n';
	errorDetails += '   ' + new Date().toLocaleString() + '\n\n';

	if (errorContext) {
		if (errorContext.url) {
			errorDetails += '📍 REQUEST URL:\n';
			errorDetails += '   ' + errorContext.url + '\n\n';
		}

		if (errorContext.params) {
			errorDetails += '📦 REQUEST PARAMETERS:\n';
			errorDetails += '   ' + errorContext.params + '\n\n';
		}

		if (errorContext.method) {
			errorDetails += '🔧 METHOD:\n';
			errorDetails += '   ' + errorContext.method + '\n\n';
		}

		if (errorContext.statusCode) {
			errorDetails += '📊 HTTP STATUS:\n';
			errorDetails += '   ' + errorContext.statusCode + '\n\n';
		}

		if (errorContext.responseText) {
			errorDetails += '📄 RESPONSE (first 500 chars):\n';
			var response = errorContext.responseText.substring(0, 500);
			errorDetails += '   ' + response + '\n';
			if (errorContext.responseText.length > 500) {
				errorDetails += '   ... (truncated, total length: ' + errorContext.responseText.length + ' chars)\n';
			}
			errorDetails += '\n';
		}

		if (errorContext.error && errorContext.error.stack) {
			errorDetails += '📋 STACK TRACE:\n';
			var stackLines = errorContext.error.stack.split('\n').slice(0, 10);
			stackLines.forEach(function(line) {
				errorDetails += '   ' + line.trim() + '\n';
			});
			errorDetails += '\n';
		}

		if (errorContext.additionalInfo) {
			errorDetails += '💡 ADDITIONAL INFO:\n';
			if (typeof errorContext.additionalInfo === 'object') {
				errorDetails += '   ' + JSON.stringify(errorContext.additionalInfo, null, 2) + '\n\n';
			} else {
				errorDetails += '   ' + errorContext.additionalInfo + '\n\n';
			}
		}
	}

	errorDetails += '═══════════════════════════════════════\n';
	errorDetails += '💡 TIP: Click "Copy" to copy this error for support\n';
	errorDetails += '═══════════════════════════════════════\n';

	// Log to console
	console.group('🚨 ' + title);
	console.error('Message:', message);
	console.error('Context:', errorContext);
	console.error('Full Details:', errorDetails);
	console.groupEnd();

	// Remove any existing error modal
	var existingModal = document.getElementById('comprehensive-error-modal');
	if (existingModal) {
		existingModal.remove();
	}

	// Create modal container
	var modal = document.createElement('div');
	modal.id = 'comprehensive-error-modal';
	modal.style.cssText = `
		position: fixed;
		top: 0;
		left: 0;
		width: 100%;
		height: 100%;
		background: rgba(0, 0, 0, 0.85);
		display: flex;
		align-items: center;
		justify-content: center;
		z-index: 999999;
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, monospace;
		animation: fadeIn 0.2s ease-in;
	`;

	// Create modal content
	var content = document.createElement('div');
	content.style.cssText = `
		background: white;
		padding: 0;
		border-radius: 12px;
		max-width: 900px;
		width: 90%;
		max-height: 90vh;
		overflow: hidden;
		box-shadow: 0 8px 32px rgba(0, 0, 0, 0.6);
		display: flex;
		flex-direction: column;
		animation: slideIn 0.3s ease-out;
	`;

	// Create header
	var header = document.createElement('div');
	header.style.cssText = `
		background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
		color: white;
		padding: 20px 25px;
		font-size: 20px;
		font-weight: bold;
		display: flex;
		justify-content: space-between;
		align-items: center;
		border-radius: 12px 12px 0 0;
		box-shadow: 0 2px 8px rgba(0,0,0,0.2);
	`;
	header.innerHTML = `
		<div style="display: flex; align-items: center; gap: 10px;">
			<span style="font-size: 24px;">🚨</span>
			<span>` + title + `</span>
		</div>
		<button id="comprehensive-error-close" style="
			background: rgba(255,255,255,0.15);
			border: 2px solid rgba(255,255,255,0.5);
			color: white;
			padding: 8px 16px;
			cursor: pointer;
			border-radius: 6px;
			font-size: 16px;
			font-weight: bold;
			transition: all 0.2s;
		" onmouseover="this.style.background='rgba(255,255,255,0.25)'"
		   onmouseout="this.style.background='rgba(255,255,255,0.15)'">✕ Close</button>
	`;

	// Create message area
	var messageArea = document.createElement('pre');
	messageArea.style.cssText = `
		white-space: pre-wrap;
		word-wrap: break-word;
		background: #f8f9fa;
		padding: 20px;
		border-radius: 0;
		border-left: 6px solid #dc3545;
		font-size: 13px;
		line-height: 1.7;
		overflow-y: auto;
		margin: 0;
		flex: 1;
		font-family: "Courier New", Courier, monospace;
	`;
	messageArea.textContent = errorDetails;

	// Create button container
	var buttonContainer = document.createElement('div');
	buttonContainer.style.cssText = `
		display: flex;
		gap: 12px;
		justify-content: flex-end;
		padding: 20px 25px;
		background: #f8f9fa;
		border-top: 1px solid #dee2e6;
		border-radius: 0 0 12px 12px;
	`;

	// Create copy button
	var copyButton = document.createElement('button');
	copyButton.textContent = '📋 Copy Error Details';
	copyButton.style.cssText = `
		background: #007bff;
		color: white;
		border: none;
		padding: 12px 24px;
		border-radius: 6px;
		cursor: pointer;
		font-size: 15px;
		font-weight: 600;
		transition: all 0.2s;
		box-shadow: 0 2px 4px rgba(0,123,255,0.3);
	`;
	copyButton.onmouseover = function() {
		this.style.background = '#0056b3';
		this.style.transform = 'translateY(-1px)';
		this.style.boxShadow = '0 4px 8px rgba(0,123,255,0.4)';
	};
	copyButton.onmouseout = function() {
		this.style.background = '#007bff';
		this.style.transform = 'translateY(0)';
		this.style.boxShadow = '0 2px 4px rgba(0,123,255,0.3)';
	};
	copyButton.onclick = function() {
		navigator.clipboard.writeText(errorDetails).then(function() {
			copyButton.textContent = '✅ Copied!';
			copyButton.style.background = '#28a745';
			setTimeout(function() {
				copyButton.textContent = '📋 Copy Error Details';
				copyButton.style.background = '#007bff';
			}, 2000);
		}).catch(function(err) {
			console.error('Failed to copy:', err);
			copyButton.textContent = '❌ Copy Failed';
			copyButton.style.background = '#dc3545';
			setTimeout(function() {
				copyButton.textContent = '📋 Copy Error Details';
				copyButton.style.background = '#007bff';
			}, 2000);
		});
	};

	// Create OK button
	var okButton = document.createElement('button');
	okButton.textContent = 'OK';
	okButton.style.cssText = `
		background: #6c757d;
		color: white;
		border: none;
		padding: 12px 32px;
		border-radius: 6px;
		cursor: pointer;
		font-size: 15px;
		font-weight: 600;
		transition: all 0.2s;
		box-shadow: 0 2px 4px rgba(108,117,125,0.3);
	`;
	okButton.onmouseover = function() {
		this.style.background = '#545b62';
		this.style.transform = 'translateY(-1px)';
		this.style.boxShadow = '0 4px 8px rgba(108,117,125,0.4)';
	};
	okButton.onmouseout = function() {
		this.style.background = '#6c757d';
		this.style.transform = 'translateY(0)';
		this.style.boxShadow = '0 2px 4px rgba(108,117,125,0.3)';
	};
	okButton.onclick = function() {
		modal.style.animation = 'fadeOut 0.2s ease-out';
		setTimeout(function() {
			modal.remove();
		}, 200);
	};

	// Assemble modal
	buttonContainer.appendChild(copyButton);
	buttonContainer.appendChild(okButton);
	content.appendChild(header);
	content.appendChild(messageArea);
	content.appendChild(buttonContainer);
	modal.appendChild(content);

	// Add close button handler
	var closeButton = header.querySelector('#comprehensive-error-close');
	closeButton.onclick = function() {
		modal.style.animation = 'fadeOut 0.2s ease-out';
		setTimeout(function() {
			modal.remove();
		}, 200);
	};

	// Close on backdrop click
	modal.onclick = function(e) {
		if (e.target === modal) {
			modal.style.animation = 'fadeOut 0.2s ease-out';
			setTimeout(function() {
				modal.remove();
			}, 200);
		}
	};

	// Close on Escape key
	var escapeHandler = function(e) {
		if (e.key === 'Escape') {
			modal.style.animation = 'fadeOut 0.2s ease-out';
			setTimeout(function() {
				modal.remove();
			}, 200);
			document.removeEventListener('keydown', escapeHandler);
		}
	};
	document.addEventListener('keydown', escapeHandler);

	// Add CSS animations
	if (!document.getElementById('error-modal-animations')) {
		var style = document.createElement('style');
		style.id = 'error-modal-animations';
		style.textContent = `
			@keyframes fadeIn {
				from { opacity: 0; }
				to { opacity: 1; }
			}
			@keyframes fadeOut {
				from { opacity: 1; }
				to { opacity: 0; }
			}
			@keyframes slideIn {
				from { transform: translateY(-50px); opacity: 0; }
				to { transform: translateY(0); opacity: 1; }
			}
		`;
		document.head.appendChild(style);
	}

	// Add to document
	document.body.appendChild(modal);

	// Focus OK button for keyboard accessibility
	okButton.focus();

	// Send error email with console logs
	sendErrorEmail(title, message, errorContext, errorDetails);
}

// Function to send error email with console logs
function sendErrorEmail(title, message, errorContext, errorDetails) {
	// Get console logs
	var consoleLogs = 'Console logging not available';
	if (typeof window.getConsoleLogs === 'function') {
		consoleLogs = window.getConsoleLogs(100); // Get last 100 console entries
	}

	// Prepare data to send
	var errorData = {
		title: title,
		errorMessage: message,
		errorDetails: errorDetails || '',
		volunteer: (typeof VolunteerID !== 'undefined' ? VolunteerID : 'Unknown'),
		callSid: (errorContext && errorContext.callSid) || 'Unknown',
		requestData: (errorContext && errorContext.params) ? errorContext.params : '{}',
		consoleLogs: consoleLogs,
		timestamp: new Date().toLocaleString(),
		url: (errorContext && errorContext.url) || window.location.href,
		stackTrace: (errorContext && errorContext.error && errorContext.error.stack) ? errorContext.error.stack : ''
	};

	// Send to error email endpoint
	fetch('sendErrorEmail.php', {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json'
		},
		body: JSON.stringify(errorData)
	}).then(function(response) {
		return response.json();
	}).then(function(data) {
		if (data.status === 'success') {
			console.log('✅ Error email sent to Tim@LGBTHotline.org');
		} else {
			console.error('❌ Failed to send error email:', data.message);
		}
	}).catch(function(error) {
		console.error('❌ Error sending email:', error);
	});
}

// ═══════════════════════════════════════════════════════════════
// INFORMATIONAL MODAL (NO EMAIL)
// For legitimate results that need user notification but aren't errors
// ═══════════════════════════════════════════════════════════════

function showInformationalModal(title, message, details) {
	// Build informational message
	var modalMessage = message;

	if (details) {
		modalMessage += '\n\n';
		if (typeof details === 'object') {
			modalMessage += JSON.stringify(details, null, 2);
		} else {
			modalMessage += details;
		}
	}

	// Log to console for debugging
	console.log('ℹ️ ' + title + ': ' + message);

	// Remove any existing modal
	var existingModal = document.getElementById('informational-modal');
	if (existingModal) {
		existingModal.remove();
	}

	// Create modal container
	var modal = document.createElement('div');
	modal.id = 'informational-modal';
	modal.style.cssText = `
		position: fixed;
		top: 0;
		left: 0;
		width: 100%;
		height: 100%;
		background: rgba(0, 0, 0, 0.85);
		display: flex;
		align-items: center;
		justify-content: center;
		z-index: 999999;
		font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
		animation: fadeIn 0.2s ease-in;
	`;

	// Create modal content
	var content = document.createElement('div');
	content.style.cssText = `
		background: white;
		padding: 0;
		border-radius: 12px;
		max-width: 600px;
		width: 90%;
		overflow: hidden;
		box-shadow: 0 8px 32px rgba(0, 0, 0, 0.6);
		display: flex;
		flex-direction: column;
		animation: slideIn 0.3s ease-out;
	`;

	// Create header (blue theme for informational)
	var header = document.createElement('div');
	header.style.cssText = `
		background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
		color: white;
		padding: 20px 25px;
		font-size: 20px;
		font-weight: bold;
		display: flex;
		justify-content: space-between;
		align-items: center;
		border-radius: 12px 12px 0 0;
		box-shadow: 0 2px 8px rgba(0,0,0,0.2);
	`;
	header.innerHTML = `
		<div style="display: flex; align-items: center; gap: 10px;">
			<span style="font-size: 24px;">ℹ️</span>
			<span>` + title + `</span>
		</div>
		<button id="informational-close" style="
			background: rgba(255,255,255,0.15);
			border: 2px solid rgba(255,255,255,0.5);
			color: white;
			padding: 8px 16px;
			cursor: pointer;
			border-radius: 6px;
			font-size: 16px;
			font-weight: bold;
			transition: all 0.2s;
		" onmouseover="this.style.background='rgba(255,255,255,0.25)'"
		   onmouseout="this.style.background='rgba(255,255,255,0.15)'">✕ Close</button>
	`;

	// Create message area
	var messageArea = document.createElement('div');
	messageArea.style.cssText = `
		background: #f8f9fa;
		padding: 30px 25px;
		font-size: 16px;
		line-height: 1.6;
		color: #333;
	`;
	messageArea.textContent = message;

	// Create button container
	var buttonContainer = document.createElement('div');
	buttonContainer.style.cssText = `
		display: flex;
		gap: 12px;
		justify-content: flex-end;
		padding: 20px 25px;
		background: #f8f9fa;
		border-top: 1px solid #dee2e6;
		border-radius: 0 0 12px 12px;
	`;

	// Create OK button
	var okButton = document.createElement('button');
	okButton.textContent = 'OK';
	okButton.style.cssText = `
		background: #17a2b8;
		color: white;
		border: none;
		padding: 12px 32px;
		border-radius: 6px;
		cursor: pointer;
		font-size: 15px;
		font-weight: 600;
		transition: all 0.2s;
		box-shadow: 0 2px 4px rgba(23,162,184,0.3);
	`;
	okButton.onmouseover = function() {
		this.style.background = '#138496';
		this.style.transform = 'translateY(-1px)';
		this.style.boxShadow = '0 4px 8px rgba(23,162,184,0.4)';
	};
	okButton.onmouseout = function() {
		this.style.background = '#17a2b8';
		this.style.transform = 'translateY(0)';
		this.style.boxShadow = '0 2px 4px rgba(23,162,184,0.3)';
	};
	okButton.onclick = function() {
		modal.style.animation = 'fadeOut 0.2s ease-out';
		setTimeout(function() {
			modal.remove();
		}, 200);
	};

	// Assemble modal
	buttonContainer.appendChild(okButton);
	content.appendChild(header);
	content.appendChild(messageArea);
	content.appendChild(buttonContainer);
	modal.appendChild(content);

	// Add close button handler
	var closeButton = header.querySelector('#informational-close');
	closeButton.onclick = function() {
		modal.style.animation = 'fadeOut 0.2s ease-out';
		setTimeout(function() {
			modal.remove();
		}, 200);
	};

	// Close on backdrop click
	modal.onclick = function(e) {
		if (e.target === modal) {
			modal.style.animation = 'fadeOut 0.2s ease-out';
			setTimeout(function() {
				modal.remove();
			}, 200);
		}
	};

	// Close on Escape key
	var escapeHandler = function(e) {
		if (e.key === 'Escape') {
			modal.style.animation = 'fadeOut 0.2s ease-out';
			setTimeout(function() {
				modal.remove();
			}, 200);
			document.removeEventListener('keydown', escapeHandler);
		}
	};
	document.addEventListener('keydown', escapeHandler);

	// Add to document
	document.body.appendChild(modal);

	// Focus OK button for keyboard accessibility
	okButton.focus();
}
