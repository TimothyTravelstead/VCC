/**
 * Twilio Voice SDK v2.x Module
 * 
 * This module provides a v1.x compatible interface for Twilio Voice SDK v2.x
 * Extracted from index.js to maintain separation of concerns
 */

// Twilio Voice SDK v2.x implementation with v1.x compatibility wrapper
window.callMonitor = (function () {
    let device = null;
    let isUnloading = false;
    let activeCallsMap = new Map(); // Track active calls for v2.x
    let currentCall = null; // Track the current active call

    // Compatibility wrapper to make v2.x device behave like v1.x
    const createDeviceWrapper = (v2Device) => {
        return {
            // Core device reference
            _device: v2Device,
            
            // v1.x compatible methods
            status: function() {
                if (v2Device.state === Twilio.Device.State.Registered) return 'ready';
                if (v2Device.state === Twilio.Device.State.Unregistered) return 'offline';
                if (v2Device.isBusy) return 'busy';
                return 'unknown';
            },
            
            connect: function(params) {
                // v2.x returns a promise, but v1.x was synchronous
                // Store the call promise for later retrieval
                const callPromise = v2Device.connect({ params });
                let callInstance = null;
                
                callPromise.then(call => {
                    callInstance = call;
                    currentCall = call; // Track the current active call

                    // Listen for ringing to capture CallSid (v2.x sends it in ringing event)
                    call.on('ringing', (hasEarlyMedia) => {
                        // In v2.x, the CallSid is available via call.sid (primary) or call.parameters.CallSid
                        // CRITICAL: Must check call.sid FIRST - this is always available for conference calls
                        const callSid = call.sid || call.parameters?.CallSid || call.outboundConnectionId;
                        if (callSid) {
                            callProxy.sid = callSid;
                            callProxy.parameters = callProxy.parameters || {};
                            callProxy.parameters.CallSid = callSid;
                            activeCallsMap.set(callSid, call);
                            console.log("📞 CallSid captured from ringing:", callSid);
                        } else {
                            console.error("🚨 [CRITICAL] CallSid NOT available in ringing event!", {
                                callSid: call.sid,
                                paramsCallSid: call.parameters?.CallSid,
                                outboundConnectionId: call.outboundConnectionId,
                                callKeys: Object.keys(call)
                            });
                        }
                    });

                    // Set up call event handlers
                    call.on('accept', () => {
                        // Try to get CallSid again on accept in case ringing didn't have it
                        // CRITICAL: Must check call.sid FIRST - this is always available
                        const callSid = call.sid || call.parameters?.CallSid || callProxy.sid;
                        if (callSid && !callProxy.sid) {
                            callProxy.sid = callSid;
                            callProxy.parameters = callProxy.parameters || {};
                            callProxy.parameters.CallSid = callSid;
                            console.log("📞 CallSid captured from accept (was missing in ringing):", callSid);
                        }
                        if (!callProxy.sid) {
                            console.error("🚨 [CRITICAL] CallSid STILL NOT available after accept!", {
                                callSid: call.sid,
                                paramsCallSid: call.parameters?.CallSid,
                                callProxySid: callProxy.sid,
                                callKeys: Object.keys(call)
                            });
                        }
                        console.log("Call accepted, CallSid:", callProxy.sid);
                        // Trigger connect handlers
                        if (this._connectHandlers) {
                            this._connectHandlers.forEach(handler => {
                                const conn = {
                                    status: () => call.status(),
                                    parameters: call.parameters
                                };
                                handler(conn);
                            });
                        }
                    });
                    
                    call.on('disconnect', () => {
                        console.log("Call disconnected, removing from tracking:", call.sid);
                        activeCallsMap.delete(call.sid);
                        if (currentCall === call) {
                            currentCall = null;
                        }
                        
                        // Trigger disconnect handlers
                        if (this._disconnectHandlers) {
                            this._disconnectHandlers.forEach(handler => {
                                const conn = {
                                    status: () => 'closed',
                                    parameters: call.parameters
                                };
                                console.log("Triggering disconnect handler for call:", call.sid);
                                handler(conn);
                            });
                        }
                    });
                }).catch(error => {
                    console.error("Failed to connect call:", error);
                });
                
                // Store pending event handlers for when callInstance becomes available
                const pendingHandlers = {
                    accept: [],
                    disconnect: [],
                    cancel: [],
                    error: []
                };

                // Return a call-like object immediately for v1.x compatibility
                const callProxy = {
                    status: () => callInstance ? callInstance.status() : 'pending',
                    parameters: params,
                    accept: () => callInstance && callInstance.accept(),
                    reject: () => callInstance && callInstance.reject(),
                    disconnect: () => callInstance && callInstance.disconnect(),
                    mute: (muted) => {
                        if (callInstance) {
                            console.log(`📞 Muting call (${muted ? 'mute' : 'unmute'}):`, callInstance.sid);
                            return callInstance.mute(muted);
                        } else {
                            console.warn(`📞 Cannot mute - callInstance not ready yet, will retry when connected`);
                            // Queue the mute action to happen when call connects
                            if (!callProxy._pendingMute) {
                                callProxy._pendingMute = muted;
                            } else {
                                callProxy._pendingMute = muted;
                            }
                            return null;
                        }
                    },
                    sendDigits: (digits) => callInstance && callInstance.sendDigits(digits),
                    getLocalStream: () => callInstance && callInstance.getLocalStream(),
                    getRemoteStream: () => callInstance && callInstance.getRemoteStream(),
                    // Add on() method for event handling - critical for training sessions
                    on: (eventName, handler) => {
                        console.log(`📞 Call proxy: registering '${eventName}' handler`);
                        if (callInstance) {
                            // Call instance already exists, register directly
                            callInstance.on(eventName, handler);
                        } else {
                            // Store handler to be registered when call instance is ready
                            if (pendingHandlers[eventName]) {
                                pendingHandlers[eventName].push(handler);
                            } else {
                                pendingHandlers[eventName] = [handler];
                            }
                        }
                    }
                };

                // When call instance becomes available, register pending handlers
                callPromise.then(call => {
                    // Register any pending event handlers
                    Object.keys(pendingHandlers).forEach(eventName => {
                        pendingHandlers[eventName].forEach(handler => {
                            // For accept handlers, pass the callProxy which has the CallSid from ringing event
                            if (eventName === 'accept') {
                                call.on(eventName, () => {
                                    console.log('📋 [CALLSID] Accept handler wrapper called', {
                                        callProxySid: callProxy.sid,
                                        callSid: call.sid,
                                        callStatus: call.status(),
                                        timestamp: new Date().toISOString()
                                    });
                                    handler({
                                        sid: callProxy.sid,
                                        parameters: { CallSid: callProxy.sid }
                                    });
                                });
                            } else {
                                call.on(eventName, handler);
                            }
                        });
                    });

                    // Apply any pending mute state
                    if (typeof callProxy._pendingMute !== 'undefined') {
                        call.mute(callProxy._pendingMute);
                        delete callProxy._pendingMute;
                    }
                });

                return callProxy;
            },
            
            disconnectAll: function() {
                // v2.x doesn't have disconnectAll, so we need to disconnect each call
                console.log("disconnectAll called");
                
                // First try our manually tracked current call
                if (currentCall) {
                    try {
                        console.log("Disconnecting current call:", currentCall.sid);
                        currentCall.disconnect();
                        return;
                    } catch (error) {
                        console.error("Error disconnecting current call:", currentCall.sid, error);
                    }
                }
                
                // Fallback: check our manual tracking map
                if (activeCallsMap.size > 0) {
                    console.log("Disconnecting calls from map:", activeCallsMap.size);
                    activeCallsMap.forEach((call, sid) => {
                        try {
                            console.log("Disconnecting call from map:", sid);
                            call.disconnect();
                        } catch (error) {
                            console.error("Error disconnecting call from map:", sid, error);
                        }
                    });
                    return;
                }
                
                // Final fallback: try the v2Device.calls collection
                const activeCalls = Array.from(v2Device.calls.values());
                console.log("Disconnecting calls from device:", activeCalls.length);
                
                if (activeCalls.length === 0) {
                    console.log("No active calls found to disconnect");
                    return;
                }
                
                activeCalls.forEach(call => {
                    try {
                        console.log("Disconnecting call from device:", call.sid);
                        call.disconnect();
                    } catch (error) {
                        console.error("Error disconnecting call from device:", call.sid, error);
                    }
                });
                
                // Return undefined like v1.x (synchronous behavior)
                return undefined;
            },
            
            activeConnection: function() {
                // v2.x doesn't have activeConnection, return first active call
                const activeCalls = Array.from(v2Device.calls.values());
                return activeCalls.length > 0 ? activeCalls[0] : null;
            },
            
            destroy: function() {
                return v2Device.destroy();
            },
            
            // v1.x style event handlers
            on: function(eventName, handler) {
                // Map v1.x events to v2.x events
                switch(eventName) {
                    case 'ready':
                        v2Device.on('registered', handler);
                        break;
                    case 'offline':
                        v2Device.on('unregistered', handler);
                        break;
                    case 'error':
                        v2Device.on('error', handler);
                        break;
                    case 'incoming':
                        v2Device.on('incoming', (call) => {
                            // Track incoming calls 
                            currentCall = call;
                            activeCallsMap.set(call.sid, call);
                            console.log("Incoming call tracked:", call.sid);
                            
                            // Set up event handlers for incoming calls
                            call.on('accept', () => {
                                console.log("Incoming call accepted:", call.sid);
                                // Trigger connect handlers
                                if (this._connectHandlers) {
                                    this._connectHandlers.forEach(handler => {
                                        const conn = {
                                            status: () => call.status(),
                                            parameters: call.parameters
                                        };
                                        handler(conn);
                                    });
                                }
                            });
                            
                            call.on('disconnect', () => {
                                console.log("Incoming call disconnected, removing from tracking:", call.sid);
                                activeCallsMap.delete(call.sid);
                                if (currentCall === call) {
                                    currentCall = null;
                                }
                                
                                // Trigger disconnect handlers
                                if (this._disconnectHandlers) {
                                    this._disconnectHandlers.forEach(handler => {
                                        const conn = {
                                            status: () => 'closed',
                                            parameters: call.parameters
                                        };
                                        console.log("Triggering disconnect handler for incoming call:", call.sid);
                                        handler(conn);
                                    });
                                }
                            });
                            
                            // Create v1.x style connection object
                            const conn = {
                                parameters: call.parameters,
                                CallSid: call.parameters.CallSid,
                                From: call.parameters.From,
                                To: call.parameters.To,
                                CallStatus: 'ringing',
                                accept: () => {
                                    console.log("Accepting call:", call.sid);
                                    return call.accept();
                                },
                                reject: () => {
                                    console.log("Rejecting call:", call.sid);
                                    // Remove from tracking when rejected
                                    activeCallsMap.delete(call.sid);
                                    if (currentCall === call) {
                                        currentCall = null;
                                    }
                                    return call.reject();
                                },
                                disconnect: () => call.disconnect(),
                                status: () => call.status()
                            };
                            handler(conn);
                        });
                        break;
                    case 'connect':
                        // Store the connect handler to be called when calls are established
                        if (!this._connectHandlers) this._connectHandlers = [];
                        this._connectHandlers.push(handler);
                        break;
                    case 'disconnect':
                        // Store the disconnect handler to be called when calls disconnect
                        if (!this._disconnectHandlers) this._disconnectHandlers = [];
                        this._disconnectHandlers.push(handler);
                        break;
                    case 'cancel':
                        // Listen for new calls and attach cancel handler
                        v2Device.on('incoming', (call) => {
                            call.on('cancel', handler);
                        });
                        break;
                    default:
                        console.warn(`Unknown event: ${eventName}`);
                }
            },
            
            // Audio device management (v2.x style)
            audio: {
                speakerDevices: {
                    set: function(deviceId) {
                        return v2Device.audio.setOutputDevice(deviceId);
                    }
                },
                resume: function() {
                    // v2.x handles audio context automatically
                    return Promise.resolve();
                }
            }
        };
    };

    return {
        initialize: async function () {
            try {
                isUnloading = false;
                // Fetch new token before initializing Twilio device
                const response = await fetch('newTwilioToken.php');
                const data = await response.json();
                console.log("Fetched new token, length:", data.token.length);

                // Log the Twilio Voice SDK version and detection info
                console.log("🔊 TWILIO VOICE SDK VERSION:", Twilio.version || "2.x (version property not available)");
                console.log("📞 Using Twilio Voice SDK v2.x with v1.x compatibility wrapper");
                
                // Additional version detection clues
                console.log("🔍 SDK Detection:");
                console.log("   - Device constructor:", typeof Twilio.Device);
                console.log("   - Device.State enum:", typeof Twilio.Device.State);
                console.log("   - Call class:", typeof Twilio.Call);
                console.log("   - Has .register() method:", typeof new Twilio.Device(data.token, {}).register === 'function');
                console.log("   - Loading from:", document.querySelector('script[src*="twilio"]')?.src || "Unknown source");

                // Initialize v2.x device with token and options
                const v2Device = new Twilio.Device(data.token, {
                    logLevel: 'debug',
                    codecPreferences: ['opus', 'pcmu'],
                    enableImprovedSignalingErrorPrecision: true,
                    // Disable Twilio's built-in incoming ringtone - we use custom chat.wav instead
                    // This prevents audio conflicts and unexpected ringing sounds
                    sounds: {
                        incoming: false
                    }
                });
                
                // Create wrapped device
                device = createDeviceWrapper(v2Device);
                
                // Set up event handlers before registering
                this.setupEventHandlers();
                
                // Register device (v2.x equivalent of setup)
                await v2Device.register();

                console.log("Twilio Device Status:", device.status());
            } catch (error) {
                console.error("Failed to initialize Twilio Device:", error);
                throw error;
            }
        },

        getDevice: function () {
            if (!device) {
                throw new Error("Twilio Device has not been initialized.");
            }
            return device;
        },

        updateToken: async function (newToken) {
            if (device && !isUnloading) {
                // v2.x has updateToken method
                await device._device.updateToken(newToken);
                console.log("Twilio token updated successfully.");
            } else {
                throw new Error("Twilio Device is not initialized or is being unloaded.");
            }
        },

        fetchNewTwilioToken: async function () {
            if (isUnloading) {
                console.log("Skip token fetch - device is unloading");
                return null;
            }

            const userID = document.getElementById("volunteerID").value;
            const url = "newTwilioToken.php";
            const params = `ZipCode=94114&UserID=${userID}`;

            try {
                const response = await fetch(url, {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: params,
                });

                if (!response.ok) {
                    throw new Error(`Failed to fetch new token: ${response.statusText}`);
                }

                const data = await response.json();
                if (!data.token) {
                    throw new Error('No token in response');
                }
                
                return data.token;
            } catch (error) {
                console.error("Error fetching Twilio token:", error);
                throw error;
            }
        },

        destroyDevice: async function () {
            if (device) {
                try {
                    // disconnectAll now handles promises internally, no need to await
                    device.disconnectAll();
                    await device.destroy();
                    console.log("Device destroyed successfully");
                } catch (error) {
                    console.error("Error destroying device:", error);
                }
            }
        },

        setupEventHandlers: function () {
            if (!device) {
                console.error("Cannot setup handlers - device not initialized");
                return;
            }

            const speakerDeviceSelect = document.getElementById('speaker-devices');

            if (speakerDeviceSelect) {
                device.on('ready', async () => {
                    if (isUnloading) return;
                    try {
                        // Resume audio context on ready
                        await device.audio.resume();
                        
                        // Update speaker devices
                        speakerDeviceSelect.innerHTML = '';
                        const availableDevices = await navigator.mediaDevices.enumerateDevices();
                        const audioOutputDevices = availableDevices.filter(device => device.kind === 'audiooutput');
                        
                        audioOutputDevices.forEach(device => {
                            const option = document.createElement('option');
                            option.value = device.deviceId;
                            option.text = device.label || `Speaker ${speakerDeviceSelect.length + 1}`;
                            speakerDeviceSelect.appendChild(option);
                        });
                    } catch (error) {
                        console.error("Error setting up audio devices:", error);
                    }
                });

                speakerDeviceSelect.addEventListener('change', (e) => {
                    if (isUnloading) return;
                    const deviceId = e.target.value;
                    if (device.audio && deviceId) {
                        device.audio.speakerDevices.set(deviceId)
                            .then(() => console.log('Speaker set successfully'))
                            .catch(error => console.error('Error setting speaker:', error));
                    }
                });
            }

            device.on('ready', () => {
                if (isUnloading) return;
                console.log("Device ready. Status:", device.status());
            });

            device.on('error', error => {
                console.error("Twilio Error:", error.message, "Code:", error.code);
            });

            device.on('connect', conn => {
                if (isUnloading) return;
                console.log("Connected. Connection state:", conn.status());
            });

            device.on('disconnect', conn => {
                if (isUnloading) return;
                if (typeof newCall !== 'undefined' && newCall && newCall.endCall) {
                    newCall.endCall();
                }
                
                // Auto-end any training conferences when calls disconnect
                if (typeof window.trainingSession !== 'undefined' && window.trainingSession && window.trainingSession.conferenceID) {
                    console.log("Training session active - auto-ending conference:", window.trainingSession.conferenceID);
                    window.trainingSession.autoEndConference();
                }
                
                console.log("Disconnected");
            });

            device.on('incoming', conn => {
                if (isUnloading) return;
                if (typeof viewControl !== 'undefined') {
                    viewControl('callPane');
                }
                console.log("Incoming connection from:", conn.parameters.From);
            });

            device.on('cancel', () => {
                if (isUnloading) return;
                console.log("Call canceled");
            });

            device.on('offline', async () => {
                if (isUnloading) {
                    console.log("Device offline while unloading - skipping token refresh");
                    return;
                }
                
                console.warn("Device offline. Attempting to refresh token...");
                try {
                    const newToken = await this.fetchNewTwilioToken();
                    if (newToken) {
                        await this.updateToken(newToken);
                        console.log("Token refreshed successfully");
                    }
                } catch (error) {
                    console.error("Failed to refresh token while offline:", error);
                }
            });
        },

        unload: async function () {
            if (device) {
                console.log("Unloading Twilio Device...");
                try {
                    isUnloading = true;
                    await this.destroyDevice();
                    device = null;
                    console.log("Twilio Device successfully unloaded.");
                } catch (error) {
                    console.error("Error during Twilio Device unload:", error);
                }
            }
        }
    };
})();

// Function to fetch a new token and update the device
window.newTwilioToken = async function() {
    const userID = document.getElementById("volunteerID").value;
    const url = "newTwilioToken.php";
    const params = `ZipCode=94114&UserID=${userID}`;

    try {
        const response = await fetch(url, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: params,
        });

        if (!response.ok) {
            throw new Error(`Failed to fetch new token: ${response.statusText}`);
        }

        const newToken = await response.text();
        document.getElementById("token").value = newToken;

        await window.callMonitor.updateToken(newToken);
    } catch (error) {
        console.error("Error updating Twilio token:", error);
    }
}

window.initializeCallMonitor = async function() {
    await window.callMonitor.initialize();
}

// Export for use in other modules if needed
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { callMonitor, newTwilioToken, initializeCallMonitor };
}