type LegacyUser = {
  role?: 'buyer' | 'seller' | 'admin' | string;
};

function getAppBasePath(): string {
  if (typeof window === 'undefined') {
    return '';
  }

  return window.location.pathname.startsWith('/marketplace/') ? '/marketplace' : '';
}

export function buildLegacyUrl(path: string): string {
  if (typeof window === 'undefined') {
    return path;
  }

  const normalizedPath = path.startsWith('/') ? path : `/${path}`;
  return `${window.location.origin}${getAppBasePath()}${normalizedPath}`;
}

export function getLegacyDashboardPath(user?: LegacyUser | null): string {
  return user?.role === 'admin' ? '/admin/' : '/dashboard.php';
}

export function getLegacyLogoutUrl(): string {
  return buildLegacyUrl('/logout.php');
}

export async function redirectToLegacyPath(path: string): Promise<void> {
  await new Promise((resolve) => window.setTimeout(resolve, 75));
  window.location.replace(buildLegacyUrl(path));
}

export async function redirectToLegacyDashboard(user?: LegacyUser | null, path?: string): Promise<void> {
  await redirectToLegacyPath(path || getLegacyDashboardPath(user));
}
