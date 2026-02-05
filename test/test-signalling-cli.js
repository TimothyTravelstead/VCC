#!/usr/bin/env node

/**
 * Command Line Test for WebRTC Signalling Server
 * Tests the optimized minimal-webrtc-signaller.js
 */

const { io } = require('socket.io-client');

const SERVER_URL = 'https://share.volunteerlogin.org';
const COLORS = {
    reset: '\x1b[0m',
    bright: '\x1b[1m',
    red: '\x1b[31m',
    green: '\x1b[32m',
    yellow: '\x1b[33m',
    blue: '\x1b[34m',
    cyan: '\x1b[36m'
};

class SignallingServerTest {
    constructor() {
        this.testResults = {
            passed: 0,
            failed: 0,
            total: 0
        };
        this.connections = [];
    }

    log(message, color = 'reset') {
        const timestamp = new Date().toLocaleTimeString();
        console.log(`${COLORS[color]}[${timestamp}] ${message}${COLORS.reset}`);
    }

    async createConnection(userId, timeout = 5000) {
        return new Promise((resolve, reject) => {
            const socket = io(SERVER_URL, {
                transports: ['polling', 'websocket'],
                forceNew: true
            });

            const timeoutId = setTimeout(() => {
                socket.disconnect();
                reject(new Error(`Connection timeout after ${timeout}ms`));
            }, timeout);

            socket.on('connect', () => {
                clearTimeout(timeoutId);
                this.connections.push(socket);
                resolve(socket);
            });

            socket.on('connect_error', (error) => {
                clearTimeout(timeoutId);
                reject(error);
            });
        });
    }

    async test(name, testFn) {
        this.testResults.total++;
        this.log(`\n🧪 Testing: ${name}`, 'cyan');
        
        try {
            const result = await testFn();
            if (result !== false) {
                this.testResults.passed++;
                this.log(`✅ PASSED: ${name}`, 'green');
                return true;
            } else {
                this.testResults.failed++;
                this.log(`❌ FAILED: ${name}`, 'red');
                return false;
            }
        } catch (error) {
            this.testResults.failed++;
            this.log(`❌ FAILED: ${name} - ${error.message}`, 'red');
            return false;
        }
    }

    async testBasicConnection() {
        const socket = await this.createConnection('test-user-basic');
        this.log(`Connected with socket ID: ${socket.id}`, 'blue');
        socket.disconnect();
        return true;
    }

    async testAuthentication() {
        const testCases = [
            {
                name: 'Valid Auth',
                userId: 'test-user-valid',
                authToken: 'valid-test-token-1234567890',
                shouldSucceed: true
            },
            {
                name: 'Invalid Token (contains "fake")',
                userId: 'test-user-fake',
                authToken: 'fake-token-1234567890',
                shouldSucceed: false
            },
            {
                name: 'Short Token',
                userId: 'test-user-short',
                authToken: 'short',
                shouldSucceed: false
            },
            {
                name: 'Missing User ID',
                userId: '',
                authToken: 'valid-test-token-1234567890',
                shouldSucceed: false
            }
        ];

        for (const testCase of testCases) {
            const socket = await this.createConnection('temp-connection');
            
            const result = await new Promise((resolve) => {
                const timeout = setTimeout(() => {
                    resolve({ success: false, reason: 'timeout' });
                }, 3000);

                socket.on('join-confirmed', (data) => {
                    clearTimeout(timeout);
                    resolve({ success: true, data });
                });

                socket.on('auth-failed', (data) => {
                    clearTimeout(timeout);
                    resolve({ success: false, reason: data.reason });
                });

                socket.on('error', (data) => {
                    clearTimeout(timeout);
                    resolve({ success: false, reason: data.message });
                });

                socket.emit('join-room', {
                    room: 'test-room-auth',
                    userId: testCase.userId,
                    authToken: testCase.authToken
                });
            });

            const passed = (result.success === testCase.shouldSucceed);
            
            if (passed) {
                this.log(`  ✅ ${testCase.name}: ${result.success ? 'Authenticated' : 'Rejected as expected'}`, 'green');
            } else {
                this.log(`  ❌ ${testCase.name}: Expected ${testCase.shouldSucceed ? 'success' : 'failure'}, got ${result.success ? 'success' : 'failure'}`, 'red');
                socket.disconnect();
                return false;
            }

            socket.disconnect();
        }

        return true;
    }

    async testRoomJoinAndSignaling() {
        // Create two test users
        const socket1 = await this.createConnection('trainer-test');
        const socket2 = await this.createConnection('trainee-test');

        // Test room joining
        const joinResults = await Promise.all([
            new Promise((resolve) => {
                const timeout = setTimeout(() => resolve(false), 5000);
                socket1.on('join-confirmed', (data) => {
                    clearTimeout(timeout);
                    resolve(data.userId === 'trainer-123');
                });
                socket1.emit('join-room', {
                    room: 'test-room-signal',
                    userId: 'trainer-123',
                    authToken: 'valid-test-token-1234567890'
                });
            }),
            new Promise((resolve) => {
                const timeout = setTimeout(() => resolve(false), 5000);
                socket2.on('join-confirmed', (data) => {
                    clearTimeout(timeout);
                    resolve(data.userId === 'trainee-456');
                });
                socket2.emit('join-room', {
                    room: 'test-room-signal',
                    userId: 'trainee-456',
                    authToken: 'valid-test-token-1234567890'
                });
            })
        ]);

        if (!joinResults.every(r => r)) {
            this.log('  ❌ Room join failed', 'red');
            socket1.disconnect();
            socket2.disconnect();
            return false;
        }

        this.log('  ✅ Both users joined room successfully', 'green');

        // Test peer notification
        const peerNotified = await new Promise((resolve) => {
            const timeout = setTimeout(() => resolve(false), 3000);
            
            socket1.on('peer-joined', (data) => {
                clearTimeout(timeout);
                resolve(data.userId === 'trainee-456');
            });

            // Trigger peer notification by having trainee join
            // (should have already happened, but let's be explicit)
        });

        // Test signal delivery
        const signalDelivered = await new Promise((resolve) => {
            const timeout = setTimeout(() => resolve(false), 3000);
            
            socket2.on('signal', (data) => {
                clearTimeout(timeout);
                resolve(
                    data.type === 'test-offer' && 
                    data.fromUserId === 'trainer-123' &&
                    data.payload.message === 'Hello trainee!'
                );
            });

            socket1.emit('signal', {
                room: 'test-room-signal',
                to: 'trainee-456',
                type: 'test-offer',
                payload: { message: 'Hello trainee!' }
            });
        });

        if (!signalDelivered) {
            this.log('  ❌ Signal delivery failed', 'red');
            socket1.disconnect();
            socket2.disconnect();
            return false;
        }

        this.log('  ✅ Signals delivered correctly', 'green');

        socket1.disconnect();
        socket2.disconnect();
        return true;
    }

    async testSessionReplacement() {
        // Create first connection
        const socket1 = await this.createConnection('session-test-1');
        
        await new Promise((resolve) => {
            socket1.on('join-confirmed', resolve);
            socket1.emit('join-room', {
                room: 'test-room-session',
                userId: 'duplicate-user',
                authToken: 'valid-test-token-1234567890'
            });
        });

        this.log('  First session established', 'blue');

        // Create second connection with same user ID
        const socket2 = await this.createConnection('session-test-2');
        
        const sessionReplaced = await new Promise((resolve) => {
            const timeout = setTimeout(() => resolve(false), 5000);
            
            socket1.on('session-replaced', (data) => {
                clearTimeout(timeout);
                resolve(true);
            });

            socket1.on('disconnect', () => {
                // This might happen instead of session-replaced
                clearTimeout(timeout);
                resolve(true);
            });

            socket2.emit('join-room', {
                room: 'test-room-session',
                userId: 'duplicate-user', // Same user ID
                authToken: 'valid-test-token-1234567890'
            });
        });

        if (!sessionReplaced) {
            this.log('  ❌ Session replacement failed', 'red');
            socket1.disconnect();
            socket2.disconnect();
            return false;
        }

        this.log('  ✅ Session replacement working correctly', 'green');
        socket2.disconnect();
        return true;
    }

    async testStressLoad() {
        const connectionCount = 5; // Smaller for CLI test
        const sockets = [];

        this.log(`  Creating ${connectionCount} connections...`, 'blue');

        // Create multiple connections
        for (let i = 0; i < connectionCount; i++) {
            const socket = await this.createConnection(`stress-user-${i}`);
            sockets.push(socket);
        }

        // Join all to same room
        await Promise.all(sockets.map((socket, index) => {
            return new Promise((resolve) => {
                socket.on('join-confirmed', resolve);
                socket.emit('join-room', {
                    room: 'stress-test-room',
                    userId: `stress-user-${index}`,
                    authToken: 'valid-test-token-1234567890'
                });
            });
        }));

        this.log('  All connections joined room', 'blue');

        // Test signal delivery
        let signalsReceived = 0;
        const expectedSignals = connectionCount; // Each socket will receive one signal

        const signalTest = await new Promise((resolve) => {
            const timeout = setTimeout(() => resolve(signalsReceived), 5000);
            
            sockets.forEach(socket => {
                socket.on('signal', () => {
                    signalsReceived++;
                    if (signalsReceived >= expectedSignals) {
                        clearTimeout(timeout);
                        resolve(signalsReceived);
                    }
                });
            });

            // Send one signal to all others
            sockets[0].emit('signal', {
                room: 'stress-test-room',
                type: 'stress-test-broadcast',
                payload: { message: 'Broadcast test' }
            });
        });

        // Clean up
        sockets.forEach(socket => socket.disconnect());

        const successRate = (signalsReceived / (connectionCount - 1)) * 100;
        
        if (successRate >= 80) {
            this.log(`  ✅ Stress test passed: ${signalsReceived}/${connectionCount - 1} signals delivered (${successRate.toFixed(1)}%)`, 'green');
            return true;
        } else {
            this.log(`  ❌ Stress test failed: ${signalsReceived}/${connectionCount - 1} signals delivered (${successRate.toFixed(1)}%)`, 'red');
            return false;
        }
    }

    async runAllTests() {
        this.log(`🚀 Starting WebRTC Signalling Server Test Suite`, 'bright');
        this.log(`📡 Target: ${SERVER_URL}`, 'blue');

        const tests = [
            { name: 'Basic Connection', fn: () => this.testBasicConnection() },
            { name: 'Authentication', fn: () => this.testAuthentication() },
            { name: 'Room Join & Signaling', fn: () => this.testRoomJoinAndSignaling() },
            { name: 'Session Replacement', fn: () => this.testSessionReplacement() },
            { name: 'Stress Test', fn: () => this.testStressLoad() }
        ];

        for (const test of tests) {
            await this.test(test.name, test.fn);
            // Brief pause between tests
            await new Promise(resolve => setTimeout(resolve, 500));
        }

        this.printSummary();
        this.cleanup();
    }

    printSummary() {
        this.log(`\n📊 Test Summary:`, 'bright');
        this.log(`Total Tests: ${this.testResults.total}`, 'blue');
        this.log(`Passed: ${this.testResults.passed}`, 'green');
        this.log(`Failed: ${this.testResults.failed}`, this.testResults.failed > 0 ? 'red' : 'green');
        
        const successRate = ((this.testResults.passed / this.testResults.total) * 100).toFixed(1);
        this.log(`Success Rate: ${successRate}%`, successRate >= 80 ? 'green' : 'red');
        
        if (this.testResults.failed === 0) {
            this.log(`\n🎉 All tests passed! Signalling server is working correctly.`, 'green');
        } else {
            this.log(`\n⚠️  Some tests failed. Check the server implementation.`, 'yellow');
        }
    }

    cleanup() {
        // Disconnect any remaining connections
        this.connections.forEach(socket => {
            if (socket.connected) {
                socket.disconnect();
            }
        });
        this.connections = [];
    }
}

// Run tests if called directly
if (require.main === module) {
    const tester = new SignallingServerTest();
    
    tester.runAllTests().catch(error => {
        console.error(`${COLORS.red}Fatal error: ${error.message}${COLORS.reset}`);
        process.exit(1);
    });
}

module.exports = SignallingServerTest;