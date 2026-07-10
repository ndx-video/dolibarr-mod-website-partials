/// <reference types="astro/client" />

interface ImportMetaEnv {
  readonly PARTIALS_BASE_URL: string;
  readonly DOLAPIKEY: string;
  readonly WEBSITE_REF: string;
  readonly PUBLIC_WEBSITE_REF: string;
  readonly PUBLIC_SLUG: string;
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}
