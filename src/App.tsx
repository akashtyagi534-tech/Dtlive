/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { useMemo, useState } from 'react';
import { Check, Copy, RefreshCw } from 'lucide-react';

const DEFAULT_APP_KEY = 'base64:SgRXHQuxu3fBwBn14NId4cY/8QO45AZ3VuqgeYgwi2c=';
const DEFAULT_API_TOKEN = 'DTLdcFLx12g5MIGu7deMJIJQTD56qcZx';

function generateRandomToken(length = 32): string {
  const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
  const bytes = new Uint8Array(length);
  crypto.getRandomValues(bytes);
  return Array.from(bytes, (b) => chars[b % chars.length]).join('');
}

function generateLaravelAppKey(): string {
  const bytes = new Uint8Array(32);
  crypto.getRandomValues(bytes);
  let binary = '';
  for (const byte of bytes) {
    binary += String.fromCharCode(byte);
  }
  return `base64:${btoa(binary)}`;
}

const REPO_FILES = [
  {
    path: 'admin_panel/',
    detail: 'Laravel 10 PHP backend & admin panel (vendor excluded, Composer builds on Railway)',
  },
  {
    path: 'database/DTLive_db_23_12_2025.sql',
    detail: 'MySQL schema and initial seed dump extracted from DTLive Database.zip',
  },
  {
    path: 'Dockerfile',
    detail: 'PHP 8.2 + Apache image with pdo_mysql, gd, zip, intl, storage:link, and $PORT binding',
  },
  {
    path: 'railway.json',
    detail: 'Configures Railway to build from Dockerfile instead of Node auto-detection',
  },
  {
    path: 'README_RAILWAY.md',
    detail: 'Step-by-step Railway deployment, MySQL import, and storage volume instructions',
  },
];

export default function App() {
  const [railwayDomain, setRailwayDomain] = useState('https://your-app.up.railway.app');
  const [apiToken, setApiToken] = useState(DEFAULT_API_TOKEN);
  const [appKey, setAppKey] = useState(DEFAULT_APP_KEY);
  const [copiedKey, setCopiedKey] = useState<'env' | 'sql' | null>(null);

  const normalizedUrl = useMemo(() => {
    const trimmed = railwayDomain.trim().replace(/\/+$/, '');
    if (!trimmed) return 'https://your-app.up.railway.app';
    return trimmed.startsWith('http://') || trimmed.startsWith('https://')
      ? trimmed
      : `https://${trimmed}`;
  }, [railwayDomain]);

  const envBlock = useMemo(() => {
    return `APP_NAME=DTLive
APP_ENV=production
APP_KEY=${appKey}
APP_DEBUG=false
APP_URL=${normalizedUrl}
WEB_URL=${normalizedUrl}

API_TOKEN=${apiToken}
PAGE_LIMIT=10
DEMO_MODE=OFF
VERSION=2.1

DB_CONNECTION=mysql
DB_HOST=\${{MySQL.MYSQLHOST}}
DB_PORT=\${{MySQL.MYSQLPORT}}
DB_DATABASE=\${{MySQL.MYSQLDATABASE}}
DB_USERNAME=\${{MySQL.MYSQLUSER}}
DB_PASSWORD=\${{MySQL.MYSQLPASSWORD}}

FILESYSTEM_DRIVER=local
SESSION_DRIVER=file
CACHE_DRIVER=file
QUEUE_CONNECTION=sync`;
  }, [appKey, apiToken, normalizedUrl]);

  const sqlImportCommand =
    'mysql -h <MYSQLHOST> -P <MYSQLPORT> -u root -p<MYSQLPASSWORD> <MYSQLDATABASE> < database/DTLive_db_23_12_2025.sql';

  const handleCopy = async (text: string, key: 'env' | 'sql') => {
    try {
      await navigator.clipboard.writeText(text);
      setCopiedKey(key);
      window.setTimeout(() => setCopiedKey(null), 2000);
    } catch {
      setCopiedKey(null);
    }
  };

  return (
    <div className="min-h-screen bg-stone-50 text-stone-900">
      <div className="mx-auto max-w-5xl px-6 py-12 md:px-10 md:py-16">
        <header className="border-b border-stone-200 pb-8">
          <div className="flex flex-wrap items-center gap-2 text-xs text-stone-500">
            <span>DTLive v2.1</span>
            <span aria-hidden="true">·</span>
            <span>Laravel 10 + PHP 8.2</span>
            <span aria-hidden="true">·</span>
            <span>Railway + GitHub</span>
          </div>
          <h1 className="mt-3 max-w-2xl text-3xl font-semibold tracking-tight text-stone-950 sm:text-4xl">
            DTLive Railway Deployment Setup
          </h1>
          <p className="mt-3 max-w-2xl text-base leading-relaxed text-stone-600">
            The admin panel, SQL dump, and Railway Docker configuration are prepared in this
            repository. Configure your Railway MySQL service and paste the generated environment
            variables below.
          </p>
        </header>

        <main className="mt-10 grid gap-10 lg:grid-cols-12">
          <section className="space-y-8 lg:col-span-5">
            <div>
              <h2 className="text-lg font-semibold text-stone-950">01. Repository Contents</h2>
              <p className="mt-1 text-sm text-stone-600">
                Extracted from your archive with nulled redirect files and local secrets removed.
              </p>
              <dl className="mt-5 divide-y divide-stone-200 border-y border-stone-200">
                {REPO_FILES.map((item) => (
                  <div key={item.path} className="py-3.5">
                    <dt className="font-mono text-xs font-medium text-stone-900">{item.path}</dt>
                    <dd className="mt-1 text-sm text-stone-600">{item.detail}</dd>
                  </div>
                ))}
              </dl>
            </div>

            <div>
              <h2 className="text-lg font-semibold text-stone-950">02. Railway Checklist</h2>
              <ol className="mt-4 space-y-3 text-sm leading-relaxed text-stone-700">
                <li>1. Sync this workspace to your connected GitHub repository.</li>
                <li>2. Create a Railway project from the GitHub repo and add a MySQL service.</li>
                <li>3. Generate a public domain under Service Settings → Networking.</li>
                <li>
                  4. Attach a Volume mounted at{' '}
                  <code className="font-mono text-xs text-stone-900">
                    /var/www/html/storage/app/public
                  </code>{' '}
                  so uploaded media persists across redeploys.
                </li>
                <li>5. Import the SQL file and paste the environment variables.</li>
              </ol>
            </div>
          </section>

          <section className="space-y-6 lg:col-span-7">
            <div className="rounded-xl border border-stone-200 bg-white p-6">
              <h2 className="text-lg font-semibold text-stone-950">
                03. Railway Variables Generator
              </h2>
              <p className="mt-1 text-sm text-stone-600">
                Enter your Railway domain to generate the exact variables block for Railway →
                Variables.
              </p>

              <div className="mt-5 space-y-4">
                <div>
                  <label
                    htmlFor="railway-domain"
                    className="block text-sm font-medium text-stone-800"
                  >
                    Railway Domain URL
                  </label>
                  <input
                    id="railway-domain"
                    type="text"
                    value={railwayDomain}
                    onChange={(e) => setRailwayDomain(e.target.value)}
                    placeholder="https://dtlive-production.up.railway.app"
                    className="mt-1.5 w-full rounded-lg border border-stone-300 bg-stone-50 px-3.5 py-2.5 font-mono text-sm text-stone-900 focus:border-stone-900 focus:bg-white focus:outline-none"
                  />
                </div>

                <div className="flex flex-wrap gap-3">
                  <button
                    type="button"
                    onClick={() => setAppKey(generateLaravelAppKey())}
                    className="inline-flex min-h-10 items-center gap-2 rounded-lg border border-stone-300 bg-white px-4 py-2 text-sm font-medium text-stone-800 transition-colors hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-stone-900"
                  >
                    <RefreshCw className="h-4 w-4" aria-hidden="true" />
                    Rotate APP_KEY
                  </button>
                  <button
                    type="button"
                    onClick={() => setApiToken(generateRandomToken(32))}
                    className="inline-flex min-h-10 items-center gap-2 rounded-lg border border-stone-300 bg-white px-4 py-2 text-sm font-medium text-stone-800 transition-colors hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-stone-900"
                  >
                    <RefreshCw className="h-4 w-4" aria-hidden="true" />
                    Rotate API_TOKEN
                  </button>
                </div>
              </div>

              <div className="mt-6">
                <div className="flex items-center justify-between">
                  <span className="text-sm font-medium text-stone-800">
                    Raw Environment Variables
                  </span>
                  <button
                    type="button"
                    onClick={() => handleCopy(envBlock, 'env')}
                    className="inline-flex min-h-10 items-center gap-2 rounded-lg bg-stone-900 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-stone-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-stone-900"
                  >
                    {copiedKey === 'env' ? (
                      <>
                        <Check className="h-4 w-4" aria-hidden="true" />
                        Copied
                      </>
                    ) : (
                      <>
                        <Copy className="h-4 w-4" aria-hidden="true" />
                        Copy Variables
                      </>
                    )}
                  </button>
                </div>
                <pre className="mt-2 overflow-x-auto rounded-lg border border-stone-200 bg-stone-950 p-4 font-mono text-xs leading-relaxed text-stone-100 tabular-nums">
                  {envBlock}
                </pre>
              </div>
            </div>

            <div className="rounded-xl border border-stone-200 bg-white p-6">
              <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                  <h2 className="text-base font-semibold text-stone-950">
                    04. MySQL Import Command
                  </h2>
                  <p className="mt-0.5 text-sm text-stone-600">
                    Run from your terminal using the Railway MySQL public connection values.
                  </p>
                </div>
                <button
                  type="button"
                  onClick={() => handleCopy(sqlImportCommand, 'sql')}
                  className="inline-flex min-h-10 items-center gap-2 rounded-lg border border-stone-300 bg-white px-4 py-2 text-sm font-medium text-stone-800 transition-colors hover:bg-stone-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-stone-900"
                >
                  {copiedKey === 'sql' ? (
                    <>
                      <Check className="h-4 w-4" aria-hidden="true" />
                      Copied
                    </>
                  ) : (
                    <>
                      <Copy className="h-4 w-4" aria-hidden="true" />
                      Copy Command
                    </>
                  )}
                </button>
              </div>
              <pre className="mt-3 overflow-x-auto rounded-lg border border-stone-200 bg-stone-950 p-4 font-mono text-xs leading-relaxed text-stone-100">
                {sqlImportCommand}
              </pre>
            </div>
          </section>
        </main>
      </div>
    </div>
  );
}
