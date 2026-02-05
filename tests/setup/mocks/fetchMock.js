/**
 * Fetch Mock
 * Provides pattern-based mocking for PHP endpoint calls
 */

// Store mock responses
const mockResponses = new Map();

// Track fetch calls for assertions
const fetchCalls = [];

/**
 * Set a mock response for a URL pattern
 * @param {string|RegExp} pattern - URL pattern to match
 * @param {Object} response - Mock response configuration
 */
function setFetchResponse(pattern, response) {
  mockResponses.set(pattern, {
    status: response.status || 200,
    statusText: response.statusText || 'OK',
    ok: response.ok !== undefined ? response.ok : (response.status || 200) < 400,
    headers: response.headers || { 'Content-Type': 'application/json' },
    body: response.body || {},
    delay: response.delay || 0
  });
}

/**
 * Clear all mock responses
 */
function clearFetchMocks() {
  mockResponses.clear();
  fetchCalls.length = 0;
}

/**
 * Get recorded fetch calls
 */
function getFetchCalls() {
  return [...fetchCalls];
}

/**
 * Get fetch calls matching a pattern
 */
function getFetchCallsMatching(pattern) {
  return fetchCalls.filter(call => {
    if (pattern instanceof RegExp) {
      return pattern.test(call.url);
    }
    return call.url.includes(pattern);
  });
}

/**
 * Find matching mock response for a URL
 */
function findMockResponse(url) {
  for (const [pattern, response] of mockResponses) {
    if (pattern instanceof RegExp && pattern.test(url)) {
      return response;
    }
    if (typeof pattern === 'string' && url.includes(pattern)) {
      return response;
    }
  }
  return null;
}

/**
 * Mock fetch implementation
 */
async function mockFetch(url, options = {}) {
  const method = options.method || 'GET';
  let body = options.body;

  // Parse JSON body if present
  if (body && typeof body === 'string') {
    try {
      body = JSON.parse(body);
    } catch (e) {
      // Keep as string if not JSON
    }
  }

  // Record the call
  fetchCalls.push({
    url,
    method,
    body,
    headers: options.headers || {},
    timestamp: Date.now()
  });

  // Find matching mock response
  const mockResponse = findMockResponse(url);

  if (!mockResponse) {
    // Return a default 404 response for unmocked URLs
    console.warn(`[fetchMock] No mock found for: ${url}`);
    return createMockResponse({
      status: 404,
      statusText: 'Not Found',
      ok: false,
      body: { error: 'No mock response configured for this URL' }
    });
  }

  // Apply delay if configured
  if (mockResponse.delay > 0) {
    await new Promise(resolve => setTimeout(resolve, mockResponse.delay));
  }

  return createMockResponse(mockResponse);
}

/**
 * Create a mock Response object
 */
function createMockResponse(config) {
  const headers = new Map(Object.entries(config.headers || {}));

  return {
    status: config.status,
    statusText: config.statusText,
    ok: config.ok,
    headers: {
      get: (name) => headers.get(name.toLowerCase()) || headers.get(name),
      has: (name) => headers.has(name.toLowerCase()) || headers.has(name)
    },
    json: () => Promise.resolve(config.body),
    text: () => Promise.resolve(
      typeof config.body === 'string' ? config.body : JSON.stringify(config.body)
    ),
    clone: function() {
      return createMockResponse(config);
    }
  };
}

/**
 * Setup common training endpoint mocks
 */
function setupTrainingEndpointMocks() {
  // getTrainingControl.php
  setFetchResponse(/getTrainingControl\.php/, {
    body: {
      success: true,
      trainerId: 'TestTrainer',
      activeController: 'TestTrainer',
      controllerRole: 'trainer',
      timestamp: Date.now()
    }
  });

  // setTrainingControl.php
  setFetchResponse(/setTrainingControl\.php/, {
    body: {
      success: true,
      message: 'Training control updated successfully'
    }
  });

  // muteConferenceParticipants.php
  setFetchResponse(/muteConferenceParticipants\.php/, {
    body: {
      success: true,
      message: 'Participant muted'
    }
  });

  // notifyCallStart.php
  setFetchResponse(/notifyCallStart\.php/, {
    body: { success: true }
  });

  // notifyCallEnd.php
  setFetchResponse(/notifyCallEnd\.php/, {
    body: { success: true }
  });

  // roomManager.php
  setFetchResponse(/roomManager\.php/, {
    body: {
      success: true,
      room: {
        roomId: 'test_room_123',
        participants: []
      }
    }
  });

  // signalingServerMulti.php
  setFetchResponse(/signalingServerMulti\.php/, {
    body: {
      success: true,
      messages: []
    }
  });
}

module.exports = {
  mockFetch,
  setFetchResponse,
  clearFetchMocks,
  getFetchCalls,
  getFetchCallsMatching,
  setupTrainingEndpointMocks
};
