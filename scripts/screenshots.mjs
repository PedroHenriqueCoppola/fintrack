import { mkdirSync, readdirSync, readFileSync, statSync, writeFileSync } from 'node:fs';
import { createConnection, createServer } from 'node:net';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const output = join(root, 'docs', 'screenshots');
const email = 'demo@fintrack.app';
const password = 'fintrack-demo';
const missingDemo = 'Rode php artisan fintrack:demo antes';

const keepChunks = new Set([
    'IHDR', 'PLTE', 'IDAT', 'IEND', 'tRNS', 'gAMA', 'sRGB', 'cHRM', 'iCCP', 'bKGD', 'pHYs',
]);

const pages = [
    { file: '01-dashboard', path: '/', chart: true },
    { file: '02-analysis', path: '/analysis', chart: true },
    { file: '03-invoices', path: '/invoices' },
    { file: '04-planning', path: '/planning' },
    { file: '05-planning-fixed', path: '/planning?tab=fixed' },
    { file: '06-goals', path: '/goals', chart: true, fullPage: true },
    { file: '07-goal-detail', goal: 'Reserva de 6 meses', chart: true, fullPage: true },
    { file: '08-net-worth', path: '/net-worth', chart: true, fullPage: true },
    { file: '09-reports', path: '/reports?period=year', chart: true, fullPage: true },
    { file: '10-manage', path: '/manage' },
    { file: '11-new-transaction', modal: true },
    { file: '12-profile', path: '/profile' },
    { file: '13-not-found', path: '/esta-pagina-nao-existe' },
];

function appUrl() {
    const fromEnv = process.env.APP_URL?.trim();

    if (fromEnv) {
        return fromEnv.replace(/\/$/, '');
    }

    try {
        const env = readFileSync(join(root, '.env'), 'utf8');
        const match = env.match(/^APP_URL=(.*)$/m);

        if (match) {
            return match[1].trim().replace(/^["']|["']$/g, '').replace(/\/$/, '');
        }
    } catch {
        // The default below is the documented address.
    }

    return 'http://localhost:8000';
}

function stripPng(file) {
    const input = readFileSync(file);
    const signature = Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]);

    if (!input.subarray(0, 8).equals(signature)) {
        throw new Error(`PNG inválido: ${file}`);
    }

    const parts = [signature];
    let offset = 8;

    while (offset + 12 <= input.length) {
        const length = input.readUInt32BE(offset);
        const type = input.subarray(offset + 4, offset + 8).toString('ascii');
        const end = offset + 12 + length;

        if (keepChunks.has(type)) {
            parts.push(input.subarray(offset, end));
        }

        offset = end;

        if (type === 'IEND') {
            break;
        }
    }

    writeFileSync(file, Buffer.concat(parts));
}

async function settle(page, { chart = false } = {}) {
    await page.waitForLoadState('load');

    try {
        await page.waitForLoadState('networkidle', { timeout: 8000 });
    } catch {
        // The Vite socket can stay open after the page itself has loaded.
    }

    await page.evaluate(() => document.fonts?.ready);
    await page.getByRole('heading').first().waitFor({ state: 'visible', timeout: 20000 });

    if (chart) {
        const surface = page.locator('.recharts-surface').first();

        try {
            await surface.waitFor({ state: 'visible', timeout: 8000 });
            await page.waitForFunction(() => {
                const svg = document.querySelector('.recharts-surface');

                return svg !== null && svg.getBoundingClientRect().height > 20;
            }, { timeout: 8000 });
        } catch {
            // A screen without a chart still has its heading.
        }
    }

    await page.waitForTimeout(400);
}

async function shot(page, theme, file, { fullPage = false } = {}) {
    const directory = join(output, theme);
    mkdirSync(directory, { recursive: true });
    const target = join(directory, `${file}.png`);
    await page.screenshot({
        path: target,
        fullPage,
        type: 'png',
        animations: 'disabled',
    });
    stripPng(target);
}

async function choose(page, label, option) {
    await page.getByRole('combobox', { name: label }).click();
    await page.getByRole('option', { name: option, exact: true }).click();
}

async function openFilledTransaction(page) {
    await page.getByRole('button', { name: 'Nova transação' }).click();
    const dialog = page.getByRole('dialog');
    await dialog.waitFor({ state: 'visible' });
    await dialog.getByLabel('Descrição').fill('Supermercado');
    await dialog.getByLabel('Valor').click();
    await page.keyboard.type('86,40', { delay: 30 });
    await choose(page, 'Categoria', 'Mercado');
    await choose(page, 'Banco', 'Nubank');
    await choose(page, 'Forma de pagamento', 'Pix');
    await page.waitForTimeout(300);
}

async function login(page, base) {
    await page.goto(`${base}/login`, { waitUntil: 'load' });
    await page.getByLabel('E-mail').fill(email);
    await page.getByLabel('Senha').fill(password);
    await page.getByRole('button', { name: 'Entrar' }).click();

    try {
        await page.waitForURL((url) => !url.pathname.startsWith('/login'), { timeout: 20000 });
    } catch {
        throw new Error(missingDemo);
    }
}

async function capture(page, base, theme) {
    for (const item of pages) {
        if (item.modal) {
            await page.goto(`${base}/`, { waitUntil: 'load' });
            await settle(page, { chart: true });
            await openFilledTransaction(page);
            await shot(page, theme, item.file);
            await page.keyboard.press('Escape');
            continue;
        }

        if (item.goal) {
            await page.goto(`${base}/goals`, { waitUntil: 'load' });
            await settle(page, { chart: true });
            await page.getByRole('link', { name: new RegExp(item.goal) }).first().click();
            await page.waitForURL(/\/goals\/\d+/);
            await settle(page, { chart: item.chart });
            await shot(page, theme, item.file, { fullPage: item.fullPage });
            continue;
        }

        await page.goto(`${base}${item.path}`, { waitUntil: 'load' });
        await settle(page, { chart: item.chart });
        await shot(page, theme, item.file, { fullPage: item.fullPage });
    }
}

function contextOptions(viewport) {
    return {
        viewport,
        deviceScaleFactor: 2,
        reducedMotion: 'reduce',
        locale: 'pt-BR',
        timezoneId: 'America/Sao_Paulo',
    };
}

async function themeContext(browser, theme, viewport) {
    const context = await browser.newContext(contextOptions(viewport));
    await context.addInitScript((value) => {
        localStorage.setItem('theme', value);
    }, theme);

    return context;
}

function report() {
    let total = 0;
    const lines = [];

    for (const theme of ['dark', 'light']) {
        const directory = join(output, theme);
        const files = readdirSync(directory).filter((name) => name.endsWith('.png')).sort();

        for (const name of files) {
            const size = statSync(join(directory, name)).size;
            total += size;
            lines.push(`${theme}/${name}  ${(size / 1024).toFixed(0)} KB`);
        }
    }

    console.log(lines.join('\n'));
    console.log(`Total: ${(total / 1024 / 1024).toFixed(1)} MB`);
}

async function responds(url) {
    try {
        const response = await fetch(url, {
            redirect: 'manual',
            signal: AbortSignal.timeout(5000),
        });

        return response.status > 0;
    } catch {
        return false;
    }
}

// Inside the vite container, localhost:8000 is not the PHP app. Vite only
// sends scripts to http://localhost:8000, so the browser has to use that
// origin. Forward it to the app service when this process cannot open it.
async function prepareBase() {
    const base = appUrl();

    if (await responds(`${base}/login`)) {
        return { base, close() {} };
    }

    if (!(await responds('http://app:8000/login'))) {
        throw new Error(`Não consegui abrir ${base}. Suba o app com docker compose up -d.`);
    }

    const server = createServer((socket) => {
        const upstream = createConnection({ host: 'app', port: 8000 });
        socket.pipe(upstream);
        upstream.pipe(socket);
        upstream.on('error', () => socket.destroy());
        socket.on('error', () => upstream.destroy());
    });

    await new Promise((resolve, reject) => {
        server.once('error', reject);
        server.listen(8000, '127.0.0.1', resolve);
    });

    if (!(await responds(`${base}/login`))) {
        server.close();
        throw new Error(`Não consegui abrir ${base}. Suba o app com docker compose up -d.`);
    }

    return {
        base,
        close() {
            server.close();
        },
    };
}

let browser;
let bridge = { close() {} };

try {
    bridge = await prepareBase();
    const base = bridge.base;
    browser = await chromium.launch({
        headless: true,
        args: ['--disable-dev-shm-usage'],
    });
    for (const theme of ['dark', 'light']) {
        const context = await themeContext(browser, theme, { width: 1440, height: 900 });
        const page = await context.newPage();
        page.setDefaultTimeout(20000);
        await page.goto(`${base}/login`, { waitUntil: 'load' });
        await settle(page);
        await shot(page, theme, '00-login');
        await login(page, base);
        await capture(page, base, theme);
        await context.close();
    }

    const tablet = await themeContext(browser, 'dark', { width: 1024, height: 768 });
    const tabletPage = await tablet.newPage();
    tabletPage.setDefaultTimeout(20000);
    await login(tabletPage, base);
    await tabletPage.goto(`${base}/`, { waitUntil: 'load' });
    await settle(tabletPage, { chart: true });
    await shot(tabletPage, 'dark', '14-dashboard-tablet');
    await tablet.close();
    report();
} catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    console.error(message.includes(missingDemo) ? message : `${message}`);
    process.exitCode = 1;
} finally {
    await browser?.close();
    bridge.close();
}
