/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { useState, useMemo, useEffect } from 'react';
import { 
  ClipboardList, 
  Search, 
  User as UserIcon, 
  Clock, 
  Package, 
  ShoppingCart, 
  CreditCard, 
  RotateCcw,
  Truck,
  Plus,
  Calendar,
  ArrowUpDown,
  Filter,
  X,
  Download,
  SlidersHorizontal,
  RefreshCw
} from 'lucide-react';
import { storage } from '../lib/storage';
import { Activity, ActivityType } from '../types';
import { format, isToday, isYesterday, subDays, startOfMonth } from 'date-fns';

export default function ActivityLog() {
  const [activities, setActivities] = useState<Activity[]>(storage.getActivities());

  useEffect(() => {
    const refreshData = () => {
      setActivities(storage.getActivities());
    };
    window.addEventListener('hysam-data-updated', refreshData);
    window.addEventListener('hysam-sync-end', refreshData);
    return () => {
      window.removeEventListener('hysam-data-updated', refreshData);
      window.removeEventListener('hysam-sync-end', refreshData);
    };
  }, []);
  
  const getTodayStr = () => format(new Date(), 'yyyy-MM-dd');

  // Filter states
  const [search, setSearch] = useState('');
  const [selectedUser, setSelectedUser] = useState<string>('all');
  const [selectedType, setSelectedType] = useState<string>('all');
  const [startDate, setStartDate] = useState<string>(getTodayStr());
  const [endDate, setEndDate] = useState<string>(getTodayStr());
  const [sortBy, setSortBy] = useState<'newest' | 'oldest'>('newest');
  const [isFiltersExpanded, setIsFiltersExpanded] = useState(true);

  // Extract unique users dynamically from the activities
  const uniqueUsers = useMemo(() => {
    const users = new Set<string>();
    activities.forEach(a => {
      if (a.userName) {
        users.add(a.userName);
      }
    });
    return Array.from(users).sort();
  }, [activities]);

  // Handle Quick Date Presets
  const handleQuickDate = (preset: 'all' | 'today' | 'yesterday' | 'thisWeek' | '7days' | '30days' | 'month' | 'thisYear') => {
    const now = new Date();
    const formatLocal = (d: Date) => {
      const yyyy = d.getFullYear();
      const mm = String(d.getMonth() + 1).padStart(2, '0');
      const dd = String(d.getDate()).padStart(2, '0');
      return `${yyyy}-${mm}-${dd}`;
    };

    switch (preset) {
      case 'all':
        setStartDate('');
        setEndDate('');
        break;
      case 'today':
        setStartDate(formatLocal(now));
        setEndDate(formatLocal(now));
        break;
      case 'yesterday': {
        const yesterday = subDays(now, 1);
        setStartDate(formatLocal(yesterday));
        setEndDate(formatLocal(yesterday));
        break;
      }
      case 'thisWeek': {
        const dayOfWeek = now.getDay();
        const start = subDays(now, dayOfWeek);
        setStartDate(formatLocal(start));
        setEndDate(formatLocal(now));
        break;
      }
      case '7days': {
        const past = subDays(now, 6);
        setStartDate(formatLocal(past));
        setEndDate(formatLocal(now));
        break;
      }
      case '30days': {
        const past = subDays(now, 29);
        setStartDate(formatLocal(past));
        setEndDate(formatLocal(now));
        break;
      }
      case 'month': {
        const start = startOfMonth(now);
        setStartDate(formatLocal(start));
        setEndDate(formatLocal(now));
        break;
      }
      case 'thisYear': {
        const start = new Date(now.getFullYear(), 0, 1);
        setStartDate(formatLocal(start));
        setEndDate(formatLocal(now));
        break;
      }
    }
  };

  // Determine if a quick preset is currently matching
  const activePreset = useMemo(() => {
    if (!startDate && !endDate) return 'all';
    
    const nowStr = format(new Date(), 'yyyy-MM-dd');
    if (startDate === nowStr && endDate === nowStr) return 'today';

    const yestStr = format(subDays(new Date(), 1), 'yyyy-MM-dd');
    if (startDate === yestStr && endDate === yestStr) return 'yesterday';

    const dayOfWeek = new Date().getDay();
    const startWeekStr = format(subDays(new Date(), dayOfWeek), 'yyyy-MM-dd');
    if (startDate === startWeekStr && endDate === nowStr) return 'thisWeek';

    const last7Str = format(subDays(new Date(), 6), 'yyyy-MM-dd');
    if (startDate === last7Str && endDate === nowStr) return '7days';

    const last30Str = format(subDays(new Date(), 29), 'yyyy-MM-dd');
    if (startDate === last30Str && endDate === nowStr) return '30days';

    const startMonthStr = format(startOfMonth(new Date()), 'yyyy-MM-dd');
    if (startDate === startMonthStr && endDate === nowStr) return 'month';

    const startYearStr = format(new Date(new Date().getFullYear(), 0, 1), 'yyyy-MM-dd');
    if (startDate === startYearStr && endDate === nowStr) return 'thisYear';

    return 'custom';
  }, [startDate, endDate]);

  // Check if any filters are active
  const hasActiveFilters = useMemo(() => {
    const todayStr = getTodayStr();
    return (
      search.trim() !== '' ||
      selectedUser !== 'all' ||
      selectedType !== 'all' ||
      startDate !== todayStr ||
      endDate !== todayStr
    );
  }, [search, selectedUser, selectedType, startDate, endDate]);

  // Reset all filters
  const handleResetFilters = () => {
    const todayStr = getTodayStr();
    setSearch('');
    setSelectedUser('all');
    setSelectedType('all');
    setStartDate(todayStr);
    setEndDate(todayStr);
    setSortBy('newest');
  };

  // Filter & Sort Logic
  const filteredActivities = useMemo(() => {
    let result = [...activities];

    // 1. Text Search
    if (search.trim()) {
      const q = search.toLowerCase();
      result = result.filter(a => 
        a.description.toLowerCase().includes(q) ||
        a.userName.toLowerCase().includes(q) ||
        a.type.toLowerCase().includes(q)
      );
    }

    // 2. User Filter
    if (selectedUser !== 'all') {
      result = result.filter(a => a.userName === selectedUser);
    }

    // 3. Activity Type Filter
    if (selectedType !== 'all') {
      result = result.filter(a => a.type === selectedType);
    }

    // 4. Date Range Filters
    if (startDate) {
      const start = new Date(startDate);
      start.setHours(0, 0, 0, 0);
      result = result.filter(a => new Date(a.timestamp) >= start);
    }
    if (endDate) {
      const end = new Date(endDate);
      end.setHours(23, 59, 59, 999);
      result = result.filter(a => new Date(a.timestamp) <= end);
    }

    // 5. Sort Order
    result.sort((a, b) => {
      const tA = new Date(a.timestamp).getTime();
      const tB = new Date(b.timestamp).getTime();
      return sortBy === 'newest' ? tB - tA : tA - tB;
    });

    return result;
  }, [activities, search, selectedUser, selectedType, startDate, endDate, sortBy]);

  // Group activities chronologically
  const groupedActivities = useMemo(() => {
    const groups: { [key: string]: Activity[] } = {};
    
    filteredActivities.forEach(activity => {
      const date = new Date(activity.timestamp);
      let groupName = '';
      
      if (isToday(date)) {
        groupName = 'Today';
      } else if (isYesterday(date)) {
        groupName = 'Yesterday';
      } else {
        groupName = format(date, 'MMMM dd, yyyy');
      }
      
      if (!groups[groupName]) {
        groups[groupName] = [];
      }
      groups[groupName].push(activity);
    });

    return groups;
  }, [filteredActivities]);

  // Export to CSV helper
  const handleExportCSV = () => {
    const headers = ['ID', 'Timestamp', 'Type', 'Description', 'User Name', 'User ID'];
    const rows = filteredActivities.map(a => [
      a.id,
      format(new Date(a.timestamp), 'yyyy-MM-dd HH:mm:ss'),
      a.type,
      a.description.replace(/"/g, '""'),
      a.userName.replace(/"/g, '""'),
      a.userId
    ]);

    const csvContent = "data:text/csv;charset=utf-8," 
      + [headers.join(','), ...rows.map(e => e.map(val => `"${val}"`).join(","))].join("\n");
    
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `activity_logs_export_${format(new Date(), 'yyyyMMdd_HHmmss')}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  // Export to JSON helper
  const handleExportJSON = () => {
    const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(filteredActivities, null, 2));
    const downloadAnchor = document.createElement('a');
    downloadAnchor.setAttribute("href", dataStr);
    downloadAnchor.setAttribute("download", `activity_logs_export_${format(new Date(), 'yyyyMMdd_HHmmss')}.json`);
    document.body.appendChild(downloadAnchor);
    downloadAnchor.click();
    downloadAnchor.remove();
  };

  const getActivityIcon = (type: ActivityType) => {
    switch (type) {
      case 'stock-update': return <Package className="text-amber-500 animate-pulse" size={18} />;
      case 'sale': return <ShoppingCart className="text-primary-theme" size={18} />;
      case 'payment': return <CreditCard className="text-accent-theme" size={18} />;
      case 'return': return <RotateCcw className="text-rose-500" size={18} />;
      case 'product-created': return <Plus className="text-indigo-500" size={18} />;
      case 'delivery': return <Truck className="text-purple-500" size={18} />;
      default: return <ClipboardList className="text-slate-500" size={18} />;
    }
  };

  const getActivityColor = (type: ActivityType) => {
    switch (type) {
      case 'stock-update': return 'bg-amber-50 border-amber-100 text-amber-700';
      case 'sale': return 'bg-primary-theme-light border-primary-theme-light text-primary-theme-hover';
      case 'payment': return 'bg-accent-theme-light border-accent-theme-light text-accent-theme-hover';
      case 'return': return 'bg-rose-50 border-rose-100 text-rose-700';
      case 'product-created': return 'bg-indigo-50 border-indigo-100 text-indigo-700';
      case 'delivery': return 'bg-purple-50 border-purple-100 text-purple-700';
      default: return 'bg-layout-theme-bg border-slate-100 text-slate-700';
    }
  };

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
          <h2 className="text-2xl font-bold text-slate-900 flex items-center gap-2">
            <ClipboardList className="text-primary-theme" />
            General Activity Log
          </h2>
          <p className="text-slate-500">Comprehensive audit trail of all system actions, state changes, and user activities</p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          {/* Export Actions */}
          <button
            onClick={handleExportCSV}
            disabled={filteredActivities.length === 0}
            className="px-4 py-2 bg-card-theme-bg hover:bg-layout-theme-bg text-slate-700 border border-slate-200 rounded-xl text-sm font-semibold flex items-center gap-2 shadow-sm transition-colors cursor-pointer disabled:opacity-50"
            id="export-csv-btn"
          >
            <Download size={16} />
            Export CSV
          </button>
          <button
            onClick={handleExportJSON}
            disabled={filteredActivities.length === 0}
            className="px-4 py-2 bg-card-theme-bg hover:bg-layout-theme-bg text-slate-700 border border-slate-200 rounded-xl text-sm font-semibold flex items-center gap-2 shadow-sm transition-colors cursor-pointer disabled:opacity-50"
            id="export-json-btn"
          >
            <Download size={16} />
            Export JSON
          </button>
          <button
            onClick={() => setIsFiltersExpanded(!isFiltersExpanded)}
            className={`px-4 py-2 rounded-xl text-sm font-semibold flex items-center gap-2 border shadow-sm transition-colors cursor-pointer ${
              isFiltersExpanded 
                ? 'bg-primary-theme-light border-primary-theme-light text-primary-theme-hover hover:bg-primary-theme-light' 
                : 'bg-card-theme-bg border-slate-200 text-slate-700 hover:bg-layout-theme-bg'
            }`}
            id="toggle-filters-btn"
          >
            <SlidersHorizontal size={16} />
            {isFiltersExpanded ? 'Hide Filters' : 'Show Filters'}
          </button>
        </div>
      </div>

      {/* Interactive Filter Control Panel */}
      {isFiltersExpanded && (
        <div className="bg-card-theme-bg rounded-2xl border border-slate-200 p-6 shadow-sm space-y-6">
          <div className="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 className="font-bold text-slate-800 flex items-center gap-2 text-sm uppercase tracking-wider">
              <Filter size={16} className="text-primary-theme" />
              Advanced Filters
            </h3>
            {hasActiveFilters && (
              <button
                onClick={handleResetFilters}
                className="text-xs font-semibold text-rose-600 hover:text-rose-700 flex items-center gap-1 transition-colors cursor-pointer"
                id="reset-filters-btn"
              >
                <X size={14} />
                Clear All Filters
              </button>
            )}
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            {/* Search filter */}
            <div className="space-y-1.5">
              <label className="text-xs font-bold text-slate-500 uppercase">Search Keywords</label>
              <div className="relative">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={16} />
                <input 
                  type="text"
                  placeholder="ID, desc, role, type..."
                  className="pl-9 pr-4 py-2 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-theme w-full bg-card-theme-bg text-sm"
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  id="search-filter-input"
                />
              </div>
            </div>

            {/* User filter */}
            <div className="space-y-1.5">
              <label className="text-xs font-bold text-slate-500 uppercase">User / Performed By</label>
              <select
                className="w-full border border-slate-200 rounded-xl px-3 py-2 bg-card-theme-bg text-sm focus:outline-none focus:ring-2 focus:ring-primary-theme"
                value={selectedUser}
                onChange={(e) => setSelectedUser(e.target.value)}
                id="user-filter-select"
              >
                <option value="all">All Users ({uniqueUsers.length})</option>
                {uniqueUsers.map(u => (
                  <option key={u} value={u}>{u}</option>
                ))}
              </select>
            </div>

            {/* Action/Type filter */}
            <div className="space-y-1.5">
              <label className="text-xs font-bold text-slate-500 uppercase">Action Type</label>
              <select
                className="w-full border border-slate-200 rounded-xl px-3 py-2 bg-card-theme-bg text-sm focus:outline-none focus:ring-2 focus:ring-primary-theme"
                value={selectedType}
                onChange={(e) => setSelectedType(e.target.value)}
                id="type-filter-select"
              >
                <option value="all">All Action Types</option>
                <option value="sale">Sale Operations</option>
                <option value="stock-update">Stock Updates (In/Out)</option>
                <option value="payment">Payments Recorded</option>
                <option value="return">Sales Returns</option>
                <option value="product-created">Product Creations</option>
                <option value="delivery">Deliveries</option>
              </select>
            </div>

            {/* Sort Order filter */}
            <div className="space-y-1.5">
              <label className="text-xs font-bold text-slate-500 uppercase">Chronological Sort</label>
              <div className="flex border border-slate-200 rounded-xl overflow-hidden">
                <button
                  onClick={() => setSortBy('newest')}
                  className={`flex-1 py-2 text-xs font-semibold flex items-center justify-center gap-1 transition-colors cursor-pointer ${
                    sortBy === 'newest' ? 'bg-primary-theme text-white' : 'bg-card-theme-bg text-slate-600 hover:bg-layout-theme-bg'
                  }`}
                  id="sort-newest-btn"
                >
                  <ArrowUpDown size={12} />
                  Newest First
                </button>
                <button
                  onClick={() => setSortBy('oldest')}
                  className={`flex-1 py-2 text-xs font-semibold flex items-center justify-center gap-1 transition-colors cursor-pointer ${
                    sortBy === 'oldest' ? 'bg-primary-theme text-white' : 'bg-card-theme-bg text-slate-600 hover:bg-layout-theme-bg'
                  }`}
                  id="sort-oldest-btn"
                >
                  <ArrowUpDown size={12} />
                  Oldest First
                </button>
              </div>
            </div>
          </div>

          {/* Date Filter & Presets Row */}
          <div className="border-t border-slate-100 pt-4 space-y-3">
            <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
              {/* Custom Date Pickers */}
              <div className="flex flex-wrap items-center gap-3">
                <div className="flex items-center gap-2">
                  <Calendar size={14} className="text-slate-400" />
                  <span className="text-xs font-bold text-slate-500 uppercase">Custom Dates:</span>
                </div>
                <input
                  type="date"
                  value={startDate}
                  onChange={(e) => setStartDate(e.target.value)}
                  className="border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-primary-theme bg-card-theme-bg"
                  id="start-date-input"
                />
                <span className="text-slate-400 text-xs">to</span>
                <input
                  type="date"
                  value={endDate}
                  onChange={(e) => setEndDate(e.target.value)}
                  className="border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-primary-theme bg-card-theme-bg"
                  id="end-date-input"
                />
              </div>

              {/* Quick Preset Buttons */}
              <div className="flex flex-wrap items-center gap-1.5">
                <span className="text-xs font-bold text-slate-500 uppercase lg:mr-1">Quick Presets:</span>
                {[
                  { key: 'today', label: 'Today (Default)' },
                  { key: 'yesterday', label: 'Yesterday' },
                  { key: 'thisWeek', label: 'This Week' },
                  { key: '7days', label: 'Last 7 Days' },
                  { key: 'month', label: 'This Month' },
                  { key: '30days', label: 'Last 30 Days' },
                  { key: 'thisYear', label: 'This Year' },
                  { key: 'all', label: 'All Time' }
                ].map((preset) => (
                  <button
                    key={preset.key}
                    onClick={() => handleQuickDate(preset.key as any)}
                    className={`px-2.5 py-1 rounded-lg text-xs font-medium transition-colors cursor-pointer ${
                      activePreset === preset.key 
                        ? 'bg-primary-theme-light text-primary-theme-hover border border-primary-theme-light' 
                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200 border border-transparent'
                    }`}
                  >
                    {preset.label}
                  </button>
                ))}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Main Logs Table / Chronological Feed */}
      <div className="bg-card-theme-bg rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
        <div className="p-6 border-b border-slate-100 flex items-center justify-between bg-layout-theme-bg/50">
          <span className="text-xs font-bold text-slate-400 uppercase tracking-widest flex items-center gap-2">
            <RefreshCw size={14} className="text-slate-400" />
            Audit Feed
          </span>
          <span className="text-xs text-slate-400 font-semibold">{filteredActivities.length} total events matching</span>
        </div>
        
        {filteredActivities.length === 0 ? (
          <div className="p-16 text-center">
            <ClipboardList size={52} className="mx-auto text-slate-200 mb-4 animate-bounce" />
            <h4 className="font-bold text-slate-700 text-lg">No activities found</h4>
            <p className="text-slate-400 text-sm mt-1 max-w-sm mx-auto">
              There are no logs matching your exact combination of search terms, filters, and dates. Try broadening your criteria.
            </p>
            {hasActiveFilters && (
              <button
                onClick={handleResetFilters}
                className="mt-4 px-4 py-2 bg-primary-theme hover:bg-primary-theme-hover text-white rounded-xl text-sm font-semibold transition-all shadow-md cursor-pointer"
                id="no-results-clear-btn"
              >
                Clear Filters
              </button>
            )}
          </div>
        ) : (
          <div className="divide-y divide-slate-100">
            {Object.keys(groupedActivities).map((groupName) => (
              <div key={groupName} className="p-0">
                {/* Group Header */}
                <div className="sticky top-0 bg-layout-theme-bg px-6 py-2.5 border-y border-slate-100 text-xs font-bold text-slate-500 uppercase tracking-wider flex items-center gap-2">
                  <Calendar size={12} className="text-slate-400" />
                  {groupName}
                </div>

                <div className="divide-y divide-slate-50">
                  {groupedActivities[groupName].map((activity) => (
                    <div 
                      key={activity.id} 
                      className="p-6 hover:bg-layout-theme-bg/70 transition-all flex items-start gap-4"
                    >
                      {/* Action Icon Badge */}
                      <div className={`p-3 rounded-xl border shrink-0 ${getActivityColor(activity.type)}`}>
                        {getActivityIcon(activity.type)}
                      </div>

                      {/* Log details */}
                      <div className="flex-1 min-w-0">
                        <div className="flex flex-col md:flex-row md:items-center justify-between gap-1 mb-1.5">
                          <span className="font-bold text-slate-900 text-sm md:text-base tracking-tight break-words">
                            {activity.description}
                          </span>
                          <span className="text-xs text-slate-400 flex items-center gap-1.5 shrink-0 bg-layout-theme-bg px-2.5 py-1 rounded-lg border border-slate-100">
                            <Clock size={12} />
                            {format(new Date(activity.timestamp), 'MMM dd, yyyy HH:mm:ss')}
                          </span>
                        </div>
                        <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs">
                          <span className="flex items-center gap-1 text-slate-500 font-medium">
                            <UserIcon size={12} className="text-slate-400" />
                            <span>Actor:</span>
                            <strong className="text-slate-700">{activity.userName}</strong>
                            <span className="text-slate-400 font-mono text-[10px]">({activity.userId})</span>
                          </span>
                          <span className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded bg-slate-100 text-slate-600 font-mono text-[10px] uppercase font-bold">
                            Type: {activity.type}
                          </span>
                          {activity.id && (
                            <span className="text-[10px] font-mono text-slate-400">
                              ID: {activity.id.substring(0, 8)}...
                            </span>
                          )}
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
