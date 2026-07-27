export type DateFilterOption = 'today' | 'yesterday' | 'thisWeek' | 'last7days' | 'thisMonth' | 'last30days' | 'lastMonth' | 'thisYear' | 'custom' | 'all';

export const matchesDateFilter = (
  dateString: string | number | undefined | null,
  filter: DateFilterOption,
  customStart?: string,
  customEnd?: string
): boolean => {
  if (filter === 'all') return true;
  if (!dateString) return false;

  const targetDate = new Date(dateString);
  if (isNaN(targetDate.getTime())) return false;

  const now = new Date();
  const todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 0, 0, 0, 0);
  const todayEnd = new Date(now.getFullYear(), now.getMonth(), now.getDate(), 23, 59, 59, 999);

  if (filter === 'today') {
    return targetDate >= todayStart && targetDate <= todayEnd;
  }

  if (filter === 'yesterday') {
    const yesterdayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 1, 0, 0, 0, 0);
    const yesterdayEnd = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 1, 23, 59, 59, 999);
    return targetDate >= yesterdayStart && targetDate <= yesterdayEnd;
  }

  if (filter === 'thisWeek') {
    const dayOfWeek = now.getDay(); // 0 is Sunday
    const startOfWeek = new Date(now.getFullYear(), now.getMonth(), now.getDate() - dayOfWeek, 0, 0, 0, 0);
    return targetDate >= startOfWeek && targetDate <= todayEnd;
  }

  if (filter === 'last7days') {
    const sevenDaysAgo = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 6, 0, 0, 0, 0);
    return targetDate >= sevenDaysAgo && targetDate <= todayEnd;
  }

  if (filter === 'thisMonth') {
    const startOfMonth = new Date(now.getFullYear(), now.getMonth(), 1, 0, 0, 0, 0);
    return targetDate >= startOfMonth && targetDate <= todayEnd;
  }

  if (filter === 'last30days') {
    const thirtyDaysAgo = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 29, 0, 0, 0, 0);
    return targetDate >= thirtyDaysAgo && targetDate <= todayEnd;
  }

  if (filter === 'lastMonth') {
    const lastMonthStart = new Date(now.getFullYear(), now.getMonth() - 1, 1, 0, 0, 0, 0);
    const lastMonthEnd = new Date(now.getFullYear(), now.getMonth(), 0, 23, 59, 59, 999);
    return targetDate >= lastMonthStart && targetDate <= lastMonthEnd;
  }

  if (filter === 'thisYear') {
    const startOfYear = new Date(now.getFullYear(), 0, 1, 0, 0, 0, 0);
    return targetDate >= startOfYear && targetDate <= todayEnd;
  }

  if (filter === 'custom') {
    if (customStart) {
      const [y, m, d] = customStart.split('-').map(Number);
      if (y && m && d) {
        const start = new Date(y, m - 1, d, 0, 0, 0, 0);
        if (targetDate < start) return false;
      }
    }
    if (customEnd) {
      const [y, m, d] = customEnd.split('-').map(Number);
      if (y && m && d) {
        const end = new Date(y, m - 1, d, 23, 59, 59, 999);
        if (targetDate > end) return false;
      }
    }
    return true;
  }

  return true;
};
