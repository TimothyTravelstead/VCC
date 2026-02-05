/**
 * Jest Global Setup
 * Initializes mocks and global test utilities
 */

// Import mocks
const { MockDevice, MockConnection, createMockCallMonitor } = require('./mocks/twilioMock');
const { MockRTCPeerConnection, MockMediaStream, mockGetDisplayMedia } = require('./mocks/webrtcMock');
const { mockFetch, clearFetchMocks, setFetchResponse } = require('./mocks/fetchMock');
const { setupTrainingDOM, cleanupDOM } = require('./mocks/domMock');

// Make mocks available globally
global.MockDevice = MockDevice;
global.MockConnection = MockConnection;
global.createMockCallMonitor = createMockCallMonitor;
global.MockRTCPeerConnection = MockRTCPeerConnection;
global.MockMediaStream = MockMediaStream;
global.mockGetDisplayMedia = mockGetDisplayMedia;
global.mockFetch = mockFetch;
global.clearFetchMocks = clearFetchMocks;
global.setFetchResponse = setFetchResponse;
global.setupTrainingDOM = setupTrainingDOM;
global.cleanupDOM = cleanupDOM;

// Mock console methods to reduce noise (but keep errors)
const originalConsoleLog = console.log;
const originalConsoleWarn = console.warn;

beforeAll(() => {
  // Suppress console.log during tests unless DEBUG_TESTS is set
  if (!process.env.DEBUG_TESTS) {
    console.log = jest.fn();
    console.warn = jest.fn();
  }

  // Setup WebRTC mocks
  global.RTCPeerConnection = MockRTCPeerConnection;
  global.RTCSessionDescription = jest.fn((desc) => desc);
  global.RTCIceCandidate = jest.fn((candidate) => candidate);

  // Setup navigator.mediaDevices
  Object.defineProperty(navigator, 'mediaDevices', {
    value: {
      getDisplayMedia: mockGetDisplayMedia,
      getUserMedia: jest.fn().mockResolvedValue(new MockMediaStream())
    },
    writable: true
  });

  // Setup fetch mock
  global.fetch = mockFetch;
});

afterAll(() => {
  console.log = originalConsoleLog;
  console.warn = originalConsoleWarn;
});

beforeEach(() => {
  // Clear all mocks before each test
  jest.clearAllMocks();
  clearFetchMocks();
});

afterEach(() => {
  // Clean up DOM after each test
  cleanupDOM();
});

// Test utilities
global.testUtils = {
  /**
   * Wait for async operations to settle
   */
  flushPromises: () => new Promise(resolve => setImmediate(resolve)),

  /**
   * Create a delay for timing tests
   */
  delay: (ms) => new Promise(resolve => setTimeout(resolve, ms)),

  /**
   * Assert that a function was called with specific arguments
   */
  assertCalledWith: (mockFn, ...args) => {
    expect(mockFn).toHaveBeenCalledWith(...args);
  },

  /**
   * Create a test training session with common setup
   */
  createTestSession: (role, options = {}) => {
    setupTrainingDOM(role, options);
    // Import TrainingSession dynamically after DOM setup
    // This allows the constructor to read the DOM values
    return require('../../trainingShare3/trainingSessionUpdated.js');
  }
};
