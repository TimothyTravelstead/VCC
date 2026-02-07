/**
 * Jest Configuration for Training Session Tests
 */

module.exports = {
  // Test environment
  testEnvironment: 'jsdom',

  // Root directory for tests
  rootDir: '..',

  // Test file patterns
  testMatch: [
    '<rootDir>/tests/unit/js/**/*.test.js',
    '<rootDir>/tests/integration/**/*.test.js'
  ],

  // Setup files
  setupFilesAfterEnv: ['<rootDir>/tests/setup/setupTests.js'],

  // Module name mapping for imports
  moduleNameMapper: {
    '^@mocks/(.*)$': '<rootDir>/tests/setup/mocks/$1',
    '^@fixtures/(.*)$': '<rootDir>/tests/fixtures/$1'
  },

  // Coverage configuration
  collectCoverageFrom: [
    'trainingShare3/**/*.js',
    '!**/node_modules/**'
  ],
  coverageDirectory: '<rootDir>/tests/coverage',
  coverageReporters: ['text', 'lcov', 'html'],
  coverageThreshold: {
    global: {
      branches: 70,
      functions: 70,
      lines: 70,
      statements: 70
    }
  },

  // Transform settings
  transform: {
    '^.+\\.js$': 'babel-jest'
  },

  // Module file extensions
  moduleFileExtensions: ['js', 'json'],

  // Verbose output
  verbose: true,

  // Test timeout
  testTimeout: 10000,

  // Clear mocks between tests
  clearMocks: true,

  // Restore mocks automatically
  restoreMocks: true
};
