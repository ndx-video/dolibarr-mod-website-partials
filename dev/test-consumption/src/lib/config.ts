export interface AppConfig {
  baseUrl: string;
  apiKey: string;
  websiteRef: string;
  publicWebsiteRef: string;
  publicSlug: string;
}

function trimSlash(url: string): string {
  return url.replace(/\/+$/, '');
}

export function getConfig(): AppConfig {
  const baseUrl = trimSlash(
    import.meta.env.PARTIALS_BASE_URL || 'http://partials.gandalf.lan',
  );
  return {
    baseUrl,
    apiKey: import.meta.env.DOLAPIKEY || '',
    websiteRef: import.meta.env.WEBSITE_REF || 'demo-partials',
    publicWebsiteRef: import.meta.env.PUBLIC_WEBSITE_REF || 'main-website',
    publicSlug: import.meta.env.PUBLIC_SLUG || 'welcome',
  };
}

export function restBase(config: AppConfig = getConfig()): string {
  return `${config.baseUrl}/api/index.php/websitepartials`;
}

export function publicPartialsBase(config: AppConfig = getConfig()): string {
  return `${config.baseUrl}/custom/websitepartials/public/partials`;
}
