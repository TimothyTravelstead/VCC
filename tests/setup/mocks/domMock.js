/**
 * DOM Mock
 * Creates training session DOM elements for testing
 */

/**
 * Setup the DOM with training-related elements
 * @param {string} role - 'trainer' or 'trainee'
 * @param {Object} options - Configuration options
 */
function setupTrainingDOM(role = 'trainer', options = {}) {
  const defaults = {
    volunteerID: role === 'trainer' ? 'TestTrainer' : 'TestTrainee',
    trainerID: 'TestTrainer',
    trainees: ['TestTrainee1', 'TestTrainee2'],
    fullName: role === 'trainer' ? 'Test Trainer' : 'Test Trainee'
  };

  const config = { ...defaults, ...options };

  // Clear existing DOM
  document.body.innerHTML = '';

  // Create hidden inputs (standard training page structure)
  const inputs = [
    { id: 'volunteerID', value: config.volunteerID },
    { id: 'trainerID', value: config.trainerID },
    { id: 'fullName', value: config.fullName },
    { id: 'role', value: role },
    { id: 'trainingMode', value: 'true' }
  ];

  inputs.forEach(({ id, value }) => {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.id = id;
    input.value = value;
    document.body.appendChild(input);
  });

  // Create trainee list (comma-separated in hidden input for trainers)
  if (role === 'trainer') {
    const traineeInput = document.createElement('input');
    traineeInput.type = 'hidden';
    traineeInput.id = 'traineeList';
    traineeInput.value = config.trainees.join(',');
    document.body.appendChild(traineeInput);
  }

  // Create video elements
  const videoContainer = document.createElement('div');
  videoContainer.id = 'videoContainer';
  videoContainer.innerHTML = `
    <video id="localVideo" autoplay muted playsinline></video>
    <video id="remoteVideo" autoplay playsinline></video>
  `;
  document.body.appendChild(videoContainer);

  // Create training control panel
  const controlPanel = document.createElement('div');
  controlPanel.id = 'trainingControlPanel';
  controlPanel.innerHTML = `
    <div id="controllerDisplay">
      <span id="currentController">${config.trainerID}</span>
    </div>
    <button id="shareScreenBtn">Share Screen</button>
    <button id="stopShareBtn" style="display:none;">Stop Sharing</button>
    <select id="controlTransferSelect">
      <option value="">Transfer control to...</option>
      ${config.trainees.map(t => `<option value="${t}">${t}</option>`).join('')}
    </select>
    <button id="transferControlBtn">Transfer</button>
  `;
  document.body.appendChild(controlPanel);

  // Create status indicators
  const statusPanel = document.createElement('div');
  statusPanel.id = 'trainingStatusPanel';
  statusPanel.innerHTML = `
    <div id="connectionStatus">Disconnected</div>
    <div id="muteStatus">Unmuted</div>
    <div id="callStatus">No Call</div>
  `;
  document.body.appendChild(statusPanel);

  // Create ARIA live region for announcements
  const liveRegion = document.createElement('div');
  liveRegion.id = 'trainingAnnouncements';
  liveRegion.setAttribute('aria-live', 'polite');
  liveRegion.setAttribute('aria-atomic', 'true');
  liveRegion.className = 'sr-only';
  document.body.appendChild(liveRegion);

  // Create trainee status container (for trainer view)
  if (role === 'trainer') {
    const traineeStatusContainer = document.createElement('div');
    traineeStatusContainer.id = 'traineeStatusContainer';
    config.trainees.forEach(trainee => {
      const traineeDiv = document.createElement('div');
      traineeDiv.className = 'trainee-status';
      traineeDiv.id = `traineeStatus_${trainee}`;
      traineeDiv.innerHTML = `
        <span class="trainee-name">${trainee}</span>
        <span class="trainee-connection">Disconnected</span>
        <video class="trainee-video" id="video_${trainee}" autoplay playsinline></video>
      `;
      traineeStatusContainer.appendChild(traineeDiv);
    });
    document.body.appendChild(traineeStatusContainer);
  }

  return config;
}

/**
 * Clean up the DOM after tests
 */
function cleanupDOM() {
  document.body.innerHTML = '';
}

/**
 * Update a DOM element's value (for input elements)
 */
function setDOMValue(id, value) {
  const element = document.getElementById(id);
  if (element) {
    if (element.tagName === 'INPUT') {
      element.value = value;
    } else {
      element.textContent = value;
    }
  }
}

/**
 * Get a DOM element's value
 */
function getDOMValue(id) {
  const element = document.getElementById(id);
  if (element) {
    return element.tagName === 'INPUT' ? element.value : element.textContent;
  }
  return null;
}

/**
 * Simulate a button click
 */
function clickButton(id) {
  const button = document.getElementById(id);
  if (button) {
    button.click();
    return true;
  }
  return false;
}

/**
 * Verify DOM state matches expected training state
 */
function verifyTrainingDOMState(expectedState) {
  const results = {
    passed: true,
    failures: []
  };

  if (expectedState.controller !== undefined) {
    const actual = getDOMValue('currentController');
    if (actual !== expectedState.controller) {
      results.passed = false;
      results.failures.push(`Controller mismatch: expected ${expectedState.controller}, got ${actual}`);
    }
  }

  if (expectedState.muteStatus !== undefined) {
    const actual = getDOMValue('muteStatus');
    if (actual !== expectedState.muteStatus) {
      results.passed = false;
      results.failures.push(`Mute status mismatch: expected ${expectedState.muteStatus}, got ${actual}`);
    }
  }

  if (expectedState.callStatus !== undefined) {
    const actual = getDOMValue('callStatus');
    if (actual !== expectedState.callStatus) {
      results.passed = false;
      results.failures.push(`Call status mismatch: expected ${expectedState.callStatus}, got ${actual}`);
    }
  }

  return results;
}

module.exports = {
  setupTrainingDOM,
  cleanupDOM,
  setDOMValue,
  getDOMValue,
  clickButton,
  verifyTrainingDOMState
};
