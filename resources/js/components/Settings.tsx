import React, { useState, useEffect } from 'react';
import { 
  Building2, 
  Mail, 
  Phone, 
  MapPin, 
  Coins, 
  Tags, 
  FileText, 
  Save, 
  AlertCircle,
  Plus,
  Trash2,
  X,
  ChevronRight,
  ShieldCheck,
  Settings as SettingsIcon,
  Bell,
  Calendar,
  Database,
  Upload,
  Download,
  RefreshCw
} from 'lucide-react';
import { storage } from '../lib/storage';
import { AppSettings, User } from '../types';
import { motion } from 'motion/react';

interface SettingsProps {
  user: User | null;
}

export default function Settings({ user }: SettingsProps) {
  const [settings, setSettings] = useState<AppSettings>(storage.getSettings());
  const [isSaving, setIsSaving] = useState(false);
  const [message, setMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
  const [newCategory, setNewCategory] = useState('');

  const [backups, setBackups] = useState<any[]>([]);
  const [loadingBackups, setLoadingBackups] = useState(false);
  const [creatingBackup, setCreatingBackup] = useState(false);
  const [restoringBackup, setRestoringBackup] = useState<string | null>(null);
  const [deletingBackup, setDeletingBackup] = useState<string | null>(null);
  const [uploadingBackup, setUploadingBackup] = useState(false);

  const fetchBackups = async () => {
    setLoadingBackups(true);
    try {
      const res = await fetch('/api/backups');
      if (res.ok) {
        const data = await res.json();
        setBackups(data);
      }
    } catch (e) {
      console.error('Failed to fetch backups:', e);
    } finally {
      setLoadingBackups(false);
    }
  };

  useEffect(() => {
    fetchBackups();
  }, []);

  const handleCreateBackup = async () => {
    setCreatingBackup(true);
    try {
      const res = await fetch('/api/backups', { method: 'POST' });
      if (res.ok) {
        window.dispatchEvent(new CustomEvent('hysam-show-toast', { 
          detail: { message: 'Database backup created successfully!', type: 'success' } 
        }));
        fetchBackups();
      } else {
        alert('Failed to create backup.');
      }
    } catch (e) {
      console.error(e);
      alert('Error creating backup.');
    } finally {
      setCreatingBackup(false);
    }
  };

  const handleRestoreBackup = async (id: string, filename: string) => {
    if (!window.confirm(`Are you sure you want to restore the system state from "${filename}"? All current unsaved local changes and database records will be replaced!`)) {
      return;
    }
    setRestoringBackup(id);
    try {
      const res = await fetch(`/api/backups/${id}/restore`, { method: 'POST' });
      const data = await res.json();
      if (res.ok) {
        alert('Database successfully restored. The application will now reload to apply the data.');
        window.location.reload();
      } else {
        alert(data.error || 'Failed to restore backup.');
      }
    } catch (e) {
      console.error(e);
      alert('Error restoring backup.');
    } finally {
      setRestoringBackup(null);
    }
  };

  const handleDeleteBackup = async (id: string, filename: string) => {
    if (!window.confirm(`Are you sure you want to permanently delete backup "${filename}"?`)) {
      return;
    }
    setDeletingBackup(id);
    try {
      const res = await fetch(`/api/backups/${id}`, { method: 'DELETE' });
      if (res.ok) {
        window.dispatchEvent(new CustomEvent('hysam-show-toast', { 
          detail: { message: 'Backup file deleted.', type: 'success' } 
        }));
        fetchBackups();
      } else {
        alert('Failed to delete backup.');
      }
    } catch (e) {
      console.error(e);
      alert('Error deleting backup.');
    } finally {
      setDeletingBackup(null);
    }
  };

  const handleUploadBackup = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    if (!window.confirm(`Are you sure you want to upload and restore "${file.name}"? All current data will be replaced!`)) {
      e.target.value = '';
      return;
    }

    setUploadingBackup(true);
    const formData = new FormData();
    formData.append('backup_file', file);

    try {
      const res = await fetch('/api/backups/upload', {
        method: 'POST',
        body: formData
      });
      const data = await res.json();
      if (res.ok) {
        alert('Backup successfully uploaded and restored. The application will now reload.');
        window.location.reload();
      } else {
        alert(data.error || 'Failed to upload and restore backup.');
      }
    } catch (e) {
      console.error(e);
      alert('Error uploading backup file.');
    } finally {
      setUploadingBackup(false);
      e.target.value = '';
    }
  };

  useEffect(() => {
    const handleTriggerSave = () => {
      handleSave();
    };
    window.addEventListener('hysam-trigger-save-settings', handleTriggerSave);
    return () => window.removeEventListener('hysam-trigger-save-settings', handleTriggerSave);
  }, [settings]); // Re-bind when settings change to have latest state in handleSave scope if needed, 
  // though handleSave uses state so it should be fine if it's not memoized too aggressively.

  const handleSave = async () => {
    setIsSaving(true);
    setMessage(null);
    try {
      storage.saveSettings(settings);
      storage.logActivity({
        type: 'activities' as any, // General log
        description: 'Updated application settings',
        userId: user?.id || 'system',
        userName: user?.name || 'System'
      });
      setMessage({ type: 'success', text: 'Settings saved successfully!' });
      // Trigger a global toast if available (we can dispatch an event)
      window.dispatchEvent(new CustomEvent('hysam-show-toast', { 
        detail: { message: 'All settings have been saved and applied!', type: 'success' } 
      }));
    } catch (error) {
      setMessage({ type: 'error', text: 'Failed to save settings.' });
    } finally {
      setIsSaving(false);
    }
  };

  const addCategory = () => {
    if (newCategory.trim() && !settings.categories.includes(newCategory.trim())) {
      setSettings({
        ...settings,
        categories: [...settings.categories, newCategory.trim()]
      });
      setNewCategory('');
    }
  };

  const removeCategory = (cat: string) => {
    setSettings({
      ...settings,
      categories: settings.categories.filter(c => c !== cat)
    });
  };

  if (user?.role !== 'admin') {
    return (
      <div className="flex flex-col items-center justify-center py-20 text-center">
        <div className="w-20 h-20 bg-amber-50 text-amber-500 rounded-full flex items-center justify-center mb-4">
          <ShieldCheck size={40} />
        </div>
        <h2 className="text-2xl font-bold text-slate-900">Access Restricted</h2>
        <p className="text-slate-500 mt-2 max-w-md">
          Only administrators have permission to access and modify application-wide settings.
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-8 pb-12">
      <div className="flex items-center justify-between sticky top-0 bg-layout-theme-bg/80 backdrop-blur-md z-10 py-4 -mx-4 px-4 sm:-mx-8 sm:px-8 border-b border-slate-200/50">
        <div>
          <h2 className="text-3xl font-bold text-slate-900 tracking-tight">App Settings</h2>
          <p className="text-slate-500 mt-1">Configure your business profile and application defaults</p>
        </div>
        <button
          onClick={handleSave}
          disabled={isSaving}
          className="flex items-center gap-2 px-6 py-3 bg-primary-theme text-white rounded-xl font-bold hover:bg-primary-theme-hover shadow-lg shadow-primary-theme-light transition-all disabled:opacity-50"
        >
          {isSaving ? <div className="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin" /> : <Save size={20} />}
          {isSaving ? 'Saving...' : 'Save Changes'}
        </button>
      </div>

      {message && (
        <motion.div 
          initial={{ opacity: 0, y: -10 }}
          animate={{ opacity: 1, y: 0 }}
          className={`p-4 rounded-xl flex items-center gap-3 ${
            message.type === 'success' ? 'bg-accent-theme-light text-accent-theme-dark border border-accent-theme-light' : 'bg-rose-50 text-rose-800 border border-rose-200'
          }`}
        >
          {message.type === 'success' ? <ShieldCheck size={20} /> : <AlertCircle size={20} />}
          <span className="font-medium">{message.text}</span>
        </motion.div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Business Profile */}
        <div className="lg:col-span-2 space-y-6">
          <div className="bg-card-theme-bg rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div className="p-6 border-b border-slate-50 flex items-center gap-3 bg-layout-theme-bg/50">
              <Building2 className="text-primary-theme" size={20} />
              <h3 className="font-bold text-slate-900">Business Profile</h3>
            </div>
            <div className="p-8 grid grid-cols-1 md:grid-cols-2 gap-6">
              <div className="space-y-2">
                <label className="text-xs font-bold text-slate-400 uppercase tracking-wider">Business Name</label>
                <div className="relative">
                  <Building2 className="absolute left-4 top-3.5 text-slate-300" size={18} />
                  <input
                    type="text"
                    value={settings.businessName}
                    onChange={(e) => setSettings({ ...settings, businessName: e.target.value })}
                    className="w-full pl-11 pr-4 py-3 bg-layout-theme-bg border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-theme focus:bg-card-theme-bg outline-none transition-all"
                  />
                </div>
              </div>
              <div className="space-y-2">
                <label className="text-xs font-bold text-slate-400 uppercase tracking-wider">Email Address</label>
                <div className="relative">
                  <Mail className="absolute left-4 top-3.5 text-slate-300" size={18} />
                  <input
                    type="email"
                    value={settings.businessEmail}
                    onChange={(e) => setSettings({ ...settings, businessEmail: e.target.value })}
                    className="w-full pl-11 pr-4 py-3 bg-layout-theme-bg border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-theme focus:bg-card-theme-bg outline-none transition-all"
                  />
                </div>
              </div>
              <div className="space-y-2">
                <label className="text-xs font-bold text-slate-400 uppercase tracking-wider">Phone Number</label>
                <div className="relative">
                  <Phone className="absolute left-4 top-3.5 text-slate-300" size={18} />
                  <input
                    type="text"
                    value={settings.businessPhone}
                    onChange={(e) => setSettings({ ...settings, businessPhone: e.target.value })}
                    className="w-full pl-11 pr-4 py-3 bg-layout-theme-bg border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-theme focus:bg-card-theme-bg outline-none transition-all"
                  />
                </div>
              </div>
              <div className="space-y-2">
                <label className="text-xs font-bold text-slate-400 uppercase tracking-wider">Physical Address</label>
                <div className="relative">
                  <MapPin className="absolute left-4 top-3.5 text-slate-300" size={18} />
                  <input
                    type="text"
                    value={settings.businessAddress}
                    onChange={(e) => setSettings({ ...settings, businessAddress: e.target.value })}
                    className="w-full pl-11 pr-4 py-3 bg-layout-theme-bg border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-theme focus:bg-card-theme-bg outline-none transition-all"
                  />
                </div>
              </div>
            </div>
          </div>

          <div className="bg-card-theme-bg rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div className="p-6 border-b border-slate-50 flex items-center gap-3 bg-layout-theme-bg/50">
              <FileText className="text-accent-theme" size={20} />
              <h3 className="font-bold text-slate-900">Reports & Invoices</h3>
            </div>
            <div className="p-8 space-y-6">
              <div className="space-y-2">
                <label className="text-xs font-bold text-slate-400 uppercase tracking-wider">Report Footer Text</label>
                <textarea
                  value={settings.reportFooter}
                  onChange={(e) => setSettings({ ...settings, reportFooter: e.target.value })}
                  rows={3}
                  className="w-full px-4 py-3 bg-layout-theme-bg border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-theme focus:bg-card-theme-bg outline-none transition-all resize-none"
                  placeholder="Appears at the bottom of exported reports and receipts"
                />
              </div>
            </div>
          </div>

          <div className="bg-card-theme-bg rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div className="p-6 border-b border-slate-50 flex items-center gap-3 bg-layout-theme-bg/50">
              <SettingsIcon className="text-purple-600" size={20} />
              <h3 className="font-bold text-slate-900">Application Typography</h3>
            </div>
            <div className="p-8">
              <div className="space-y-4 max-w-md">
                <div className="space-y-2">
                  <label className="text-xs font-bold text-slate-400 uppercase tracking-wider">Font Family</label>
                  <select 
                    value={settings.fontFamily}
                    onChange={(e) => setSettings({...settings, fontFamily: e.target.value as any})}
                    className="w-full px-4 py-3 bg-layout-theme-bg border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-theme focus:bg-card-theme-bg outline-none transition-all"
                  >
                    <option value="Inter">Inter (Clean & Professional)</option>
                    <option value="Plus Jakarta Sans">Plus Jakarta Sans (Modern & Soft)</option>
                    <option value="Space Grotesk">Space Grotesk (Tech & Brutalist)</option>
                    <option value="JetBrains Mono">JetBrains Mono (Developer Focused)</option>
                    <option value="Playfair Display">Playfair Display (Premium & Classic)</option>
                  </select>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* System Config */}
        <div className="space-y-6">
          <div className="bg-card-theme-bg rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div className="p-6 border-b border-slate-50 flex items-center gap-3 bg-layout-theme-bg/50">
              <ShieldCheck className="text-orange-600" size={20} />
              <h3 className="font-bold text-slate-900">System Policies</h3>
            </div>
            <div className="p-6 space-y-6">
              <div className="space-y-2">
                <label className="text-xs font-bold text-slate-400 uppercase tracking-wider flex items-center justify-between">
                  Return/Edit Period
                  <span className="text-primary-theme bg-primary-theme-light px-2 py-0.5 rounded text-[10px]">{settings.transactionEditLimitDays} Days</span>
                </label>
                <div className="relative">
                  <Calendar className="absolute left-4 top-3.5 text-slate-300" size={18} />
                  <input
                    type="number"
                    value={settings.transactionEditLimitDays || ''}
                    onChange={(e) => setSettings({ ...settings, transactionEditLimitDays: e.target.value === '' ? 0 : parseInt(e.target.value) })}
                    className="w-full pl-11 pr-4 py-3 bg-layout-theme-bg border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-theme focus:bg-card-theme-bg outline-none transition-all"
                  />
                </div>
                <p className="text-[10px] text-slate-400 font-medium leading-relaxed italic">
                  Number of days after a sale was recorded during which it can be returned or modified.
                </p>
              </div>
            </div>
          </div>

          <div className="bg-card-theme-bg rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div className="p-6 border-b border-slate-50 flex items-center gap-3 bg-layout-theme-bg/50">
              <SettingsIcon className="text-orange-600" size={20} />
              <h3 className="font-bold text-slate-900">System Defaults</h3>
            </div>
            <div className="p-6 space-y-6">
              <div className="space-y-2">
                <label className="text-xs font-bold text-slate-400 uppercase tracking-wider">Currency Symbol</label>
                <div className="relative">
                  <Coins className="absolute left-4 top-3.5 text-slate-300" size={18} />
                  <input
                    type="text"
                    value={settings.currency}
                    onChange={(e) => setSettings({ ...settings, currency: e.target.value })}
                    className="w-full pl-11 pr-4 py-3 bg-layout-theme-bg border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-theme focus:bg-card-theme-bg outline-none transition-all"
                  />
                </div>
              </div>
              <div className="space-y-2">
                <label className="text-xs font-bold text-slate-400 uppercase tracking-wider flex items-center justify-between">
                  Low Stock Alert
                  <span className="text-primary-theme bg-primary-theme-light px-2 py-0.5 rounded text-[10px]">{settings.lowStockThreshold} Units</span>
                </label>
                <div className="relative">
                  <Bell className="absolute left-4 top-3.5 text-slate-300" size={18} />
                  <input
                    type="number"
                    value={settings.lowStockThreshold || ''}
                    onChange={(e) => setSettings({ ...settings, lowStockThreshold: e.target.value === '' ? 0 : parseInt(e.target.value) })}
                    className="w-full pl-11 pr-4 py-3 bg-layout-theme-bg border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary-theme focus:bg-card-theme-bg outline-none transition-all"
                  />
                </div>
              </div>
            </div>
          </div>

          <div className="bg-card-theme-bg rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div className="p-6 border-b border-slate-50 flex items-center gap-3 bg-layout-theme-bg/50">
              <Tags className="text-primary-theme" size={20} />
              <h3 className="font-bold text-slate-900">Inventory Categories</h3>
            </div>
            <div className="p-6 space-y-4">
              <div className="flex flex-wrap gap-2">
                {settings.categories.map((cat) => (
                  <div key={cat} className="group flex items-center gap-1 bg-slate-100 text-slate-700 px-3 py-1.5 rounded-lg text-sm font-medium border border-slate-200 hover:bg-slate-200 transition-all">
                    {cat}
                    <button 
                      onClick={() => removeCategory(cat)}
                      className="text-slate-400 hover:text-rose-500 opacity-0 group-hover:opacity-100 transition-all"
                    >
                      <X size={14} />
                    </button>
                  </div>
                ))}
              </div>
              <div className="flex gap-2 pt-2">
                <input
                  type="text"
                  value={newCategory}
                  onChange={(e) => setNewCategory(e.target.value)}
                  onKeyPress={(e) => e.key === 'Enter' && addCategory()}
                  placeholder="New category..."
                  className="flex-1 px-3 py-2 bg-layout-theme-bg border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-primary-theme focus:bg-card-theme-bg outline-none transition-all"
                />
                <button
                  onClick={addCategory}
                  className="p-2 bg-primary-theme text-white rounded-xl hover:bg-primary-theme-hover transition-all shadow-sm"
                >
                  <Plus size={20} />
                </button>
              </div>
            </div>
          </div>

          {/* Backup & Recovery Module */}
          <div className="bg-card-theme-bg rounded-2xl shadow-sm border border-slate-100 overflow-hidden mb-6">
            <div className="p-6 border-b border-slate-50 flex items-center justify-between bg-layout-theme-bg/50">
              <div className="flex items-center gap-3">
                <Database className="text-primary-theme" size={20} />
                <h3 className="font-bold text-slate-900">Database Backup & Recovery</h3>
              </div>
              <button
                type="button"
                onClick={handleCreateBackup}
                disabled={creatingBackup}
                className="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition-all disabled:opacity-50 flex items-center gap-1.5 cursor-pointer"
              >
                {creatingBackup ? (
                  <div className="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                ) : (
                  <Plus size={14} />
                )}
                Create Backup
              </button>
            </div>
            <div className="p-6 space-y-6">
              <p className="text-xs text-slate-500 leading-relaxed">
                Create database backups to restore your business suite state at any time. You can download backups locally, or upload a previously downloaded backup JSON file to restore the database.
              </p>

              {/* Upload & Restore Form */}
              <div className="p-4 bg-slate-50 border border-slate-200/60 rounded-xl flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div>
                  <h4 className="text-xs font-bold text-slate-700">Upload & Restore Backup File</h4>
                  <p className="text-[11px] text-slate-400 mt-0.5">Restore the application state by uploading a JSON backup file.</p>
                </div>
                <div className="relative">
                  <input
                    type="file"
                    accept=".json"
                    id="upload-backup-input"
                    onChange={handleUploadBackup}
                    className="hidden"
                    disabled={uploadingBackup}
                  />
                  <label
                    htmlFor="upload-backup-input"
                    className="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs font-bold rounded-xl transition-all flex items-center gap-1.5 cursor-pointer disabled:opacity-50 inline-flex items-center"
                  >
                    {uploadingBackup ? (
                      <div className="w-3.5 h-3.5 border-2 border-slate-700/30 border-t-slate-700 rounded-full animate-spin mr-1.5" />
                    ) : (
                      <Upload size={14} className="mr-1.5" />
                    )}
                    Upload Backup (.json)
                  </label>
                </div>
              </div>

              {/* Backups List */}
              <div className="space-y-3">
                <h4 className="text-xs font-bold text-slate-400 uppercase tracking-wider">Available Backups</h4>
                {loadingBackups ? (
                  <div className="py-8 flex items-center justify-center text-slate-400 gap-2">
                    <div className="w-5 h-5 border-2 border-slate-300 border-t-slate-600 rounded-full animate-spin" />
                    <span className="text-xs font-medium">Loading backups...</span>
                  </div>
                ) : backups.length === 0 ? (
                  <div className="py-8 text-center border border-dashed border-slate-200 rounded-xl text-slate-400">
                    <p className="text-xs font-medium">No backups created yet.</p>
                  </div>
                ) : (
                  <div className="border border-slate-200/60 rounded-xl overflow-hidden divide-y divide-slate-100">
                    {backups.map((b) => (
                      <div key={b.id} className="p-4 flex items-center justify-between gap-4 bg-white hover:bg-slate-50/50 transition-colors">
                        <div className="space-y-1">
                          <p className="text-xs font-bold text-slate-800 break-all">{b.filename}</p>
                          <div className="flex flex-wrap gap-x-3 gap-y-1 text-[10px] text-slate-400 font-medium">
                            <span>Size: {(b.size / 1024).toFixed(2)} KB</span>
                            <span>•</span>
                            <span>Created By: {b.created_by}</span>
                            <span>•</span>
                            <span>{new Date(b.created_at).toLocaleString()}</span>
                          </div>
                        </div>
                        <div className="flex items-center gap-2 shrink-0">
                          <a
                            href={`/api/backups/${b.id}/download`}
                            className="p-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg transition-all"
                            title="Download Backup JSON"
                          >
                            <Download size={15} />
                          </a>
                          <button
                            type="button"
                            onClick={() => handleRestoreBackup(b.id, b.filename)}
                            disabled={restoringBackup !== null}
                            className="p-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-600 rounded-lg transition-all"
                            title="Restore Database to this Point"
                          >
                            {restoringBackup === b.id ? (
                              <div className="w-3.5 h-3.5 border-2 border-indigo-600/30 border-t-indigo-600 rounded-full animate-spin" />
                            ) : (
                              <RefreshCw size={15} />
                            )}
                          </button>
                          <button
                            type="button"
                            onClick={() => handleDeleteBackup(b.id, b.filename)}
                            disabled={deletingBackup !== null}
                            className="p-2 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-lg transition-all"
                            title="Delete Backup File"
                          >
                            {deletingBackup === b.id ? (
                              <div className="w-3.5 h-3.5 border-2 border-rose-600/30 border-t-rose-600 rounded-full animate-spin" />
                            ) : (
                              <Trash2 size={15} />
                            )}
                          </button>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>
          </div>

          <div className="bg-rose-50/60 rounded-2xl border border-rose-200 p-6 space-y-3">
            <div className="flex items-center gap-2.5 text-rose-800 font-bold">
              <Trash2 size={20} className="text-rose-600" />
              <h3>Clear & Reset Database</h3>
            </div>
            <p className="text-xs text-rose-700/80 leading-relaxed">
              Resets all transactions, sales orders, stock logs, returns, and payment histories to start with a clean slate for testing.
            </p>
            <button
              type="button"
              onClick={async () => {
                if (window.confirm('Are you sure you want to clear all transactions, sales, returns, and reset stock to test clean?')) {
                  await storage.clearAllData();
                  window.dispatchEvent(new CustomEvent('hysam-show-toast', { 
                    detail: { message: 'Database reset successfully! All test records cleared.', type: 'success' } 
                  }));
                }
              }}
              className="px-4 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl font-bold text-xs shadow-sm transition-all cursor-pointer flex items-center gap-2"
            >
              <Trash2 size={16} />
              <span>Clear All Test Transactions</span>
            </button>
          </div>
        </div>
      </div>

      <div className="pt-8 flex justify-end mb-20">
        <button
          onClick={handleSave}
          disabled={isSaving}
          className="flex items-center gap-2 px-8 py-4 bg-primary-theme text-white rounded-xl font-bold hover:bg-primary-theme-hover shadow-xl shadow-primary-theme-light transition-all disabled:opacity-50"
        >
          {isSaving ? <div className="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin" /> : <Save size={24} />}
          {isSaving ? 'Saving...' : 'Save All Settings'}
        </button>
      </div>

      {/* Floating Save Button - Always visible for quick access */}
      <div className="fixed bottom-8 right-8 z-[100]">
        <button
          onClick={handleSave}
          disabled={isSaving}
          className="w-14 h-14 md:w-16 md:h-16 bg-primary-theme text-white rounded-full shadow-2xl flex flex-col items-center justify-center hover:scale-110 active:scale-95 transition-all disabled:opacity-50 group border-4 border-white"
          title="Save All Changes"
        >
          {isSaving ? (
            <div className="w-6 h-6 border-2 border-white/30 border-t-white rounded-full animate-spin" />
          ) : (
            <>
              <Save size={24} className="group-hover:animate-bounce" />
              <span className="text-[8px] font-bold uppercase mt-0.5">Save</span>
            </>
          )}
        </button>
      </div>
    </div>
  );
}
