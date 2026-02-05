	// Enhanced page unload handler in chatFrame.php
	window.addEventListener('beforeunload', function() {
		// Mark user as offline when leaving
		if (navigator.sendBeacon) {
			const formData = new FormData();
			formData.append('action', 'user_leaving');
			formData.append('userID', document.getElementById('userID').value);
			formData.append('chatRoomID', document.getElementById('groupChatRoomID').value);
			
			navigator.sendBeacon('userDeparture.php', formData);
		} else {
			// Fallback for browsers that don't support sendBeacon
			const xhr = new XMLHttpRequest();
			xhr.open('POST', 'userDeparture.php', false); // Synchronous for unload
			xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
			xhr.send(`action=user_leaving&userID=${document.getElementById('userID').value}&chatRoomID=${document.getElementById('groupChatRoomID').value}`);
		}
	});

	// Also mark as offline on page visibility change (when tab is closed/minimized for extended time)
	document.addEventListener('visibilitychange', function() {
		if (document.hidden) {
			// Page is hidden, start a timer
			setTimeout(() => {
				if (document.hidden) {
					// Still hidden after 2 minutes, mark as inactive
					fetch('userDeparture.php', {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: `action=user_inactive&userID=${document.getElementById('userID').value}&chatRoomID=${document.getElementById('groupChatRoomID').value}`
					});
				}
			}, 120000); // 2 minutes
		}
	});

    // Periodic cleanup check (every 2 minutes)
    setInterval(function() {
        fetch('chatCleanup.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        }).catch(error => {
            console.log('Cleanup check failed:', error);
        });
    }, 120000); // 2 minutes

    // Emoji picker functionality
    class EmojiPicker {
        constructor() {
            this.isOpen = false;
            this.emojis = [
                // Smileys & People
                '😀', '😃', '😄', '😁', '😆', '😅', '🤣', '😂', '🙂', '🙃',
                '😉', '😊', '😇', '🥰', '😍', '🤩', '😘', '😗', '😚', '😙',
                '🥲', '😋', '😛', '😜', '🤪', '😝', '🤑', '🤗', '🤭', '🤫',
                '🤔', '🤐', '🤨', '😐', '😑', '😶', '😏', '😒', '🙄', '😬',
                
                // Hand gestures
                '👍', '👎', '👌', '✌️', '🤞', '🤟', '🤘', '🤙', '👈', '👉',
                '👆', '👇', '☝️', '👋', '🤚', '🖐️', '✋', '👏', '🙌', '🤲',
                '🤝', '🙏', '✍️', '💪',
                
                // Hearts & Symbols
                '❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '🤎', '💔',
                '❣️', '💕', '💞', '💓', '💗', '💖', '💘', '💝', '⭐', '🌟',
                '✨', '⚡', '☄️', '💫', '🔥', '💥', '💯', '💢',
                
                // Common objects
                '☕', '🍕', '🎉', '🎊', '🎈', '🎁', '🎂', '🍰', '🎵', '🎶',
                '📱', '💻', '📚', '✏️', '📝', '📊', '📈', '📉', '🔔', '🔕'
            ];
            this.createEmojiPicker();
            this.setupEventListeners();
        }

        createEmojiPicker() {
            // Create emoji picker container
            this.picker = document.createElement('div');
            this.picker.className = 'emoji-picker';
            this.picker.style.cssText = `
                position: absolute;
                bottom: 60px;
                left: 0;
                width: 300px;
                height: 250px;
                background: white;
                border: 2px solid #770000;
                border-radius: 12px;
                padding: 12px;
                box-shadow: 0 10px 30px rgba(85, 0, 0, 0.3);
                z-index: 1001;
                display: none;
                overflow-y: auto;
            `;

            // Create emoji grid
            const emojiGrid = document.createElement('div');
            emojiGrid.className = 'emoji-grid';
            emojiGrid.style.cssText = `
                display: grid;
                grid-template-columns: repeat(8, 1fr);
                gap: 4px;
                height: 100%;
            `;

            // Add emojis to grid
            this.emojis.forEach(emoji => {
                const emojiButton = document.createElement('button');
                emojiButton.className = 'emoji-button';
                emojiButton.textContent = emoji;
                emojiButton.style.cssText = `
                    background: none;
                    border: none;
                    font-size: 20px;
                    padding: 4px;
                    cursor: pointer;
                    border-radius: 4px;
                    transition: background-color 0.2s ease;
                    height: 32px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                `;

                // Add hover effect
                emojiButton.addEventListener('mouseenter', () => {
                    emojiButton.style.backgroundColor = '#f0f0f0';
                });

                emojiButton.addEventListener('mouseleave', () => {
                    emojiButton.style.backgroundColor = 'transparent';
                });

                // Add click handler
                emojiButton.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.insertEmoji(emoji);
                    this.hidePicker();
                });

                emojiGrid.appendChild(emojiButton);
            });

            this.picker.appendChild(emojiGrid);

            // Add custom scrollbar styles
            const style = document.createElement('style');
            style.textContent = `
                .emoji-picker::-webkit-scrollbar {
                    width: 6px;
                }
                .emoji-picker::-webkit-scrollbar-track {
                    background: #f1f1f1;
                    border-radius: 3px;
                }
                .emoji-picker::-webkit-scrollbar-thumb {
                    background: #770000;
                    border-radius: 3px;
                }
                .emoji-picker::-webkit-scrollbar-thumb:hover {
                    background: #990000;
                }
            `;
            document.head.appendChild(style);

            // Add to input container
            const inputContainer = document.querySelector('.input-container');
            if (inputContainer) {
                inputContainer.style.position = 'relative';
                inputContainer.appendChild(this.picker);
            }
        }

        setupEventListeners() {
            // Find the emoji button
            const emojiButton = document.querySelector('.action-button');
            if (emojiButton) {
                emojiButton.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.togglePicker();
                });
            }

            // Close picker when clicking outside
            document.addEventListener('click', (e) => {
                if (this.isOpen && !this.picker.contains(e.target) && !e.target.closest('.action-button')) {
                    this.hidePicker();
                }
            });

            // Close picker on escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.isOpen) {
                    this.hidePicker();
                }
            });
        }

        togglePicker() {
            if (this.isOpen) {
                this.hidePicker();
            } else {
                this.showPicker();
            }
        }

        showPicker() {
            this.picker.style.display = 'block';
            this.isOpen = true;
            
            // Animate in
            this.picker.style.opacity = '0';
            this.picker.style.transform = 'translateY(10px)';
            
            requestAnimationFrame(() => {
                this.picker.style.transition = 'opacity 0.2s ease, transform 0.2s ease';
                this.picker.style.opacity = '1';
                this.picker.style.transform = 'translateY(0)';
            });
        }

        hidePicker() {
            this.picker.style.opacity = '0';
            this.picker.style.transform = 'translateY(10px)';
            
            setTimeout(() => {
                this.picker.style.display = 'none';
                this.isOpen = false;
            }, 200);
        }

        insertEmoji(emoji) {
            const textArea = document.getElementById('groupChatTypingWindow');
            if (textArea) {
                // Remove empty class if present
                textArea.classList.remove('empty');
                
                // Insert emoji at cursor position or at the end
                if (document.getSelection && document.getSelection().rangeCount > 0) {
                    const selection = document.getSelection();
                    const range = selection.getRangeAt(0);
                    
                    // Check if the selection is within our text area
                    if (textArea.contains(range.commonAncestorContainer)) {
                        range.deleteContents();
                        const emojiNode = document.createTextNode(emoji);
                        range.insertNode(emojiNode);
                        
                        // Move cursor after the emoji
                        range.setStartAfter(emojiNode);
                        range.setEndAfter(emojiNode);
                        selection.removeAllRanges();
                        selection.addRange(range);
                    } else {
                        // If no selection in text area, append to end
                        textArea.textContent += emoji;
                    }
                } else {
                    // Fallback: append to end
                    textArea.textContent += emoji;
                }
                
                // Trigger input event for any listeners
                const event = new Event('input', { bubbles: true });
                textArea.dispatchEvent(event);
                
                // Focus back to text area
                textArea.focus();
            }
        }
    }

    // Initialize emoji picker when page loads
    window.addEventListener('load', function() {
        // Wait for other initialization to complete
        setTimeout(() => {
            new EmojiPicker();
        }, 500);
    });
