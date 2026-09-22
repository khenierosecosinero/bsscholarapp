import os from 'node:os';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

function lanIPv4() {
    const fromEnv = process.env.VITE_DEV_HOST?.trim();

    if (fromEnv) {
        return fromEnv;
    }

    const found = [];

    for (const addrs of Object.values(os.networkInterfaces())) {
        for (const net of addrs ?? []) {
            const family = String(net.family);
            const ip = net.address;

            if (net.internal || (family !== 'IPv4' && family !== '4')) {
                continue;
            }

            if (ip === '127.0.0.1' || ip.startsWith('169.254.')) {
                continue;
            }

            found.push(ip);
        }
    }

    found.sort((a, b) => rank(a) - rank(b));

    return found[0] ?? '127.0.0.1';
}

function rank(ip) {
    if (ip.startsWith('192.168.')) {
        return 0;
    }

    if (ip.startsWith('10.')) {
        return 1;
    }

    if (/^172\.(1[6-9]|2\d|3[0-1])\./.test(ip)) {
        return 2;
    }

    return 3;
}

const lanHost = lanIPv4();

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/css/styles.css', 'resources/js/app.js', 'resources/js/user-app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: false,
        cors: true,
        allowedHosts: true,
        origin: `http://${lanHost}:5173`,
        hmr: {
            host: lanHost,
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
