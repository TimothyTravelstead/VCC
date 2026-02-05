document.addEventListener('DOMContentLoaded', function() {
    const trainerID = document.getElementById('trainerID').value;
    const userID = document.getElementById('userID').value;
    let currentController = null;
    let isTrainer = false;

    // Hide loading overlay after content loads
    const loadingOverlay = document.getElementById('loadingOverlay');
    setTimeout(() => {
        if (loadingOverlay) {
            loadingOverlay.classList.add('hidden');
            setTimeout(() => {
                loadingOverlay.remove();
            }, 350);
        }
    }, 1000);

    // Initialize control panel and start polling
    initializeControlPanel();
    startControlPolling();

    function initializeControlPanel() {
        // Determine if current user is trainer
        isTrainer = (userID === trainerID);
        
        // Get initial controller from page
        const controllerSpan = document.querySelector('.current-controller');
        if (controllerSpan && controllerSpan.textContent.includes('Controller:')) {
            const match = controllerSpan.textContent.match(/Controller: (.+)/);
            if (match) {
                currentController = match[1].trim();
            }
        }
        
        console.log(`Training Control initialized: User=${userID}, Trainer=${trainerID}, IsTrainer=${isTrainer}`);
    }

    function startControlPolling() {
        // Poll for control changes and participant updates every 5 seconds
        setInterval(async () => {
            await pollControlStatus();
            await pollParticipantStatus();
        }, 5000);
        
        // Initial polls
        pollControlStatus();
        pollParticipantStatus();
    }

    async function pollControlStatus() {
        try {
            const response = await fetch(`/trainingShare3/getTrainingControl.php?trainerId=${encodeURIComponent(trainerID)}`);
            if (!response.ok) {
                console.error('Failed to poll control status:', response.status);
                return;
            }
            
            const data = await response.json();
            if (data.success) {
                updateControlDisplay(data);
            }
        } catch (error) {
            console.error('Error polling control status:', error);
        }
    }

    async function pollParticipantStatus() {
        try {
            const response = await fetch(`/trainingShare3/getParticipants.php?trainerId=${encodeURIComponent(trainerID)}`);
            if (!response.ok) {
                console.error('Failed to poll participant status:', response.status);
                return;
            }
            
            const data = await response.json();
            if (data.success) {
                updateParticipantDisplay(data.participants);
            }
        } catch (error) {
            console.error('Error polling participant status:', error);
        }
    }

    function updateParticipantDisplay(participants) {
        // Update participant online/offline status
        participants.forEach(participant => {
            const participantCard = document.querySelector(`[data-participant-id="${participant.id}"]`);
            if (participantCard) {
                // Update online/offline class
                if (participant.isSignedOn) {
                    participantCard.classList.add('online');
                    participantCard.classList.remove('offline');
                } else {
                    participantCard.classList.add('offline');
                    participantCard.classList.remove('online');
                }
                
                // Update status dot
                const statusDot = participantCard.querySelector('.status-dot');
                if (statusDot) {
                    if (participant.isSignedOn) {
                        statusDot.classList.add('online');
                        statusDot.classList.remove('offline');
                    } else {
                        statusDot.classList.add('offline');
                        statusDot.classList.remove('online');
                    }
                }
                
                // Update status text
                const participantStatus = participantCard.querySelector('.participant-status');
                if (participantStatus) {
                    const statusTextElements = participantStatus.childNodes;
                    for (let node of statusTextElements) {
                        if (node.nodeType === Node.TEXT_NODE && node.textContent.trim()) {
                            node.textContent = participant.isSignedOn ? 'Online' : 'Offline';
                            break;
                        }
                    }
                }
                
                // Update control status (this might have changed too)
                if (participant.hasControl) {
                    participantCard.classList.add('has-control');
                    // Add control indicator if not present
                    if (!participantCard.querySelector('.control-indicator')) {
                        const controlIndicator = document.createElement('div');
                        controlIndicator.className = 'control-indicator';
                        controlIndicator.textContent = 'IN CONTROL';
                        participantCard.appendChild(controlIndicator);
                    }
                } else {
                    participantCard.classList.remove('has-control');
                    // Remove control indicator if present
                    const controlIndicator = participantCard.querySelector('.control-indicator');
                    if (controlIndicator) {
                        controlIndicator.remove();
                    }
                }
            }
        });
        
        console.log('Participant status updated:', participants.length, 'participants');
    }

    function updateControlDisplay(controlData) {
        const newController = controlData.activeController;
        
        if (newController !== currentController) {
            console.log(`Control changed: ${currentController} -> ${newController}`);
            currentController = newController;
            
            // Update the control status display
            const controllerSpan = document.querySelector('.current-controller');
            if (controllerSpan) {
                if (newController) {
                    // Find the participant name
                    const participantCard = document.querySelector(`[data-participant-id="${newController}"]`);
                    const participantName = participantCard ? 
                        participantCard.querySelector('.participant-name').textContent :
                        newController;
                    controllerSpan.textContent = `Controller: ${participantName}`;
                } else {
                    controllerSpan.textContent = 'No controller assigned';
                }
            }
            
            // Update participant cards
            updateParticipantCards(newController);
            
            // Show notification for control change
            if (newController) {
                const participantCard = document.querySelector(`[data-participant-id="${newController}"]`);
                const participantName = participantCard ? 
                    participantCard.querySelector('.participant-name').textContent :
                    newController;
                
                if (newController === userID) {
                    showMessage(`You now have control! You will share your screen and receive external calls.`, 'success');
                } else {
                    showMessage(`${participantName} now has control.`, 'info');
                }
            }
        }
    }

    function updateParticipantCards(activeController) {
        const participantCards = document.querySelectorAll('.participant-card');
        
        participantCards.forEach(card => {
            const participantId = card.getAttribute('data-participant-id');
            const hasControl = (participantId === activeController);
            
            // Update has-control class
            if (hasControl) {
                card.classList.add('has-control');
            } else {
                card.classList.remove('has-control');
            }
            
            // Update or remove control indicator
            let controlIndicator = card.querySelector('.control-indicator');
            if (hasControl) {
                if (!controlIndicator) {
                    controlIndicator = document.createElement('div');
                    controlIndicator.className = 'control-indicator';
                    controlIndicator.textContent = 'IN CONTROL';
                    card.appendChild(controlIndicator);
                }
            } else if (controlIndicator) {
                controlIndicator.remove();
            }
        });
    }

    // Global function for transferring control (called from PHP onclick)
    window.transferControl = async function(newControllerId, newControllerName) {
        if (!isTrainer) {
            showMessage('Only trainers can transfer control', 'error');
            return;
        }
        
        if (newControllerId === currentController) {
            // If they already have control, show a different message
            showMessage(`${newControllerName} already has control`, 'info');
            return;
        }
        
        if (!confirm(`Transfer control to ${newControllerName}?\n\nThey will:\n• Share their screen\n• Receive external calls\n• Have speaking privileges during calls`)) {
            return;
        }
        
        // Show loading state
        const participantCard = document.querySelector(`[data-participant-id="${newControllerId}"]`);
        if (participantCard) {
            participantCard.style.opacity = '0.7';
            participantCard.style.pointerEvents = 'none';
        }
        
        try {
            const response = await fetch('/trainingShare3/setTrainingControl.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    trainerId: trainerID,
                    activeController: newControllerId,
                    controllerRole: (newControllerId === trainerID) ? 'trainer' : 'trainee'
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            if (data.success) {
                showMessage(`Control transferred to ${newControllerName}`, 'success');
                // Immediately refresh the control status
                await pollControlStatus();
            } else {
                showMessage(data.error || 'Failed to transfer control', 'error');
            }
        } catch (error) {
            console.error('Error transferring control:', error);
            showMessage('Failed to transfer control. Please try again.', 'error');
        } finally {
            // Remove loading state
            if (participantCard) {
                participantCard.style.opacity = '';
                participantCard.style.pointerEvents = '';
            }
        }
    };

    function showMessage(message, type = 'info') {
        // Remove any existing messages
        const existingMessage = document.querySelector('.control-message');
        if (existingMessage) {
            existingMessage.remove();
        }
        
        // Create new message element
        const messageEl = document.createElement('div');
        messageEl.className = `control-message ${type}`;
        messageEl.textContent = message;
        
        // Insert at top of control panel
        const controlPanel = document.querySelector('.training-control-panel');
        if (controlPanel) {
            controlPanel.insertBefore(messageEl, controlPanel.firstChild);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (messageEl.parentNode) {
                    messageEl.remove();
                }
            }, 5000);
        }
    }

    // Enhanced error handling for fetch requests
    window.addEventListener('unhandledrejection', function(event) {
        console.error('Unhandled promise rejection:', event.reason);
        if (event.reason && event.reason.toString().includes('fetch')) {
            showMessage('Connection error. Please check your internet connection.', 'error');
        }
    });
});

// Legacy compatibility - if old functions are called, redirect to new system
window.sendTrainingControl = function(action, target) {
    console.warn('Legacy sendTrainingControl called - this functionality is now handled by the new control system');
    return Promise.resolve();
};

window.getParticipantName = function(participantId) {
    const participantCard = document.querySelector(`[data-participant-id="${participantId}"]`);
    return participantCard ? 
        participantCard.querySelector('.participant-name').textContent :
        participantId;
};

window.showNotification = function(message, type) {
    // Redirect to new message system
    const messageEvent = new CustomEvent('showControlMessage', {
        detail: { message, type }
    });
    document.dispatchEvent(messageEvent);
};