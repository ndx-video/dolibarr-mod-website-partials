export interface PublicIsland {
  slug: string;
  title: string;
  body: string;
  updatedAt?: string | null;
}

export interface RestStatus {
  module: string;
  enabled: boolean;
  dolibarr_version: string;
  website_module: boolean;
  db_ok: boolean;
}

export interface RestWebsite {
  id: number;
  ref: string;
  description: string;
  status: number;
  lang: string;
  otherlang: string;
  virtualhost: string;
}

export interface RestPage {
  id: number;
  slug: string;
  title: string;
  body?: string;
  status: number;
  type: string;
  description?: string;
  lang?: string;
  updatedAt?: string | null;
}

export type TestStatus = 'pass' | 'fail' | 'skip';

export interface TestResult {
  id: string;
  name: string;
  group: 'rest' | 'public';
  status: TestStatus;
  httpStatus?: number;
  detail: string;
  bodyPreview?: string;
  durationMs: number;
}

export interface SuiteReport {
  startedAt: string;
  finishedAt: string;
  results: TestResult[];
  summary: { pass: number; fail: number; skip: number };
}
