/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useState, useEffect } from 'react';
import { User, UserRole, RoleConfig, ModuleCRUDPermission, ModulePermissionsMap } from '../types';
import { storage } from '../lib/storage';
import { 
  ALL_SYSTEM_MODULES, 
  getAllRoles, 
  getRoleConfig, 
  createEmptyModulePermissions, 
  createFullModulePermissions,
  hasModulePermission
} from '../lib/rbac';
import { 
  Plus, 
  Edit2, 
  Shield, 
  Trash2, 
  UserCheck, 
  UserX, 
  Check, 
  X, 
  ShieldAlert,
  Mail,
  User as UserIcon,
  Search,
  Key,
  Eye,
  EyeOff,
  Sparkles,
  LayoutDashboard,
  Package,
  ShoppingCart,
  CreditCard,
  Truck,
  RotateCcw,
  Users as UsersIcon,
  Settings as SettingsIcon,
  CheckSquare,
  Square,
  Lock,
  Users2,
  SlidersHorizontal,
  PlusCircle,
  Copy,
  Info,
  Layers,
  CheckCircle2
} from 'lucide-react';

interface UsersProps {
  currentUser: User;
}

const MODULE_ICONS: Record<string, any> = {
  dashboard: LayoutDashboard,
  inventory: Package,
  sales: ShoppingCart,
  payments: CreditCard,
  deliveries: Truck,
  activities: RotateCcw,
  users: UsersIcon,
  settings: SettingsIcon,
};

export default function Users({ currentUser }: UsersProps) {
  const canCreateUser = hasModulePermission(currentUser, 'users', 'create');
  const canEditUser = hasModulePermission(currentUser, 'users', 'edit');
  const canDeleteUser = hasModulePermission(currentUser, 'users', 'delete');

  const [activeTab, setActiveTab] = useState<'users' | 'roles'>('users');
  const [users, setUsers] = useState<User[]>(storage.getUsers());
  const [roles, setRoles] = useState<RoleConfig[]>(getAllRoles());

  useEffect(() => {
    const refreshData = () => {
      setUsers(storage.getUsers());
      setRoles(getAllRoles());
    };
    window.addEventListener('hysam-data-updated', refreshData);
    window.addEventListener('hysam-sync-end', refreshData);
    return () => {
      window.removeEventListener('hysam-data-updated', refreshData);
      window.removeEventListener('hysam-sync-end', refreshData);
    };
  }, []);
  const [search, setSearch] = useState('');

  // Staff Account Modal States
  const [isAddUserOpen, setIsAddUserOpen] = useState(false);
  const [isEditUserOpen, setIsEditUserOpen] = useState(false);
  const [editingUser, setEditingUser] = useState<User | null>(null);

  // Staff Form State
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [role, setRole] = useState<UserRole>('staff');
  const [disabled, setDisabled] = useState(false);
  const [staffModulePermissions, setStaffModulePermissions] = useState<ModulePermissionsMap>(createEmptyModulePermissions());

  // Role Customization Modal States
  const [isRoleModalOpen, setIsRoleModalOpen] = useState(false);
  const [editingRole, setEditingRole] = useState<RoleConfig | null>(null);
  const [roleLabel, setRoleLabel] = useState('');
  const [roleDesc, setRoleDesc] = useState('');
  const [roleBadgeBg, setRoleBadgeBg] = useState('bg-indigo-100');
  const [roleBadgeText, setRoleBadgeText] = useState('text-indigo-800');
  const [roleBadgeBorder, setRoleBadgeBorder] = useState('border-indigo-200');
  const [roleModulePermissions, setRoleModulePermissions] = useState<ModulePermissionsMap>(createEmptyModulePermissions());

  const [error, setError] = useState<string | null>(null);
  const [successMsg, setSuccessMsg] = useState<string | null>(null);

  const refreshRoles = () => {
    setRoles(getAllRoles());
  };

  const resetStaffForm = () => {
    setName('');
    setEmail('');
    setPassword('');
    setShowPassword(false);
    setRole('staff');
    setDisabled(false);
    const staffRoleCfg = getRoleConfig('staff');
    setStaffModulePermissions(staffRoleCfg.modulePermissions || createEmptyModulePermissions());
    setError(null);
  };

  const generateRandomPassword = () => {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$';
    let pass = 'Hysam#';
    for (let i = 0; i < 6; i++) {
      pass += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    setPassword(pass);
    setShowPassword(true);
  };

  const handleRoleSelectForStaff = (selectedRole: string) => {
    setRole(selectedRole);
    const cfg = getRoleConfig(selectedRole);
    if (cfg && cfg.modulePermissions) {
      setStaffModulePermissions(JSON.parse(JSON.stringify(cfg.modulePermissions)));
    } else {
      setStaffModulePermissions(createEmptyModulePermissions());
    }
  };

  const toggleStaffModuleAction = (moduleId: string, action: 'view' | 'create' | 'edit' | 'delete') => {
    setStaffModulePermissions(prev => {
      const next = { ...prev };
      const currentMod = next[moduleId] || { view: false, create: false, edit: false, delete: false };
      const updatedValue = !currentMod[action];

      // If enabling create/edit/delete, automatically enable view
      let updatedView = currentMod.view;
      if (updatedValue && (action === 'create' || action === 'edit' || action === 'delete')) {
        updatedView = true;
      }
      // If disabling view, automatically disable create/edit/delete
      let updatedCreate = currentMod.create;
      let updatedEdit = currentMod.edit;
      let updatedDelete = currentMod.delete;
      if (action === 'view' && !updatedValue) {
        updatedCreate = false;
        updatedEdit = false;
        updatedDelete = false;
      } else if (action !== 'view') {
        if (action === 'create') updatedCreate = updatedValue;
        if (action === 'edit') updatedEdit = updatedValue;
        if (action === 'delete') updatedDelete = updatedValue;
      }

      next[moduleId] = {
        view: updatedView,
        create: updatedCreate,
        edit: updatedEdit,
        delete: updatedDelete
      };
      return next;
    });
  };

  const batchUpdateStaffPermissions = (action: 'view' | 'create' | 'edit' | 'delete' | 'all' | 'clear') => {
    setStaffModulePermissions(prev => {
      const next = { ...prev };
      ALL_SYSTEM_MODULES.forEach(m => {
        if (action === 'all') {
          next[m.id] = { view: true, create: true, edit: true, delete: true };
        } else if (action === 'clear') {
          next[m.id] = { view: false, create: false, edit: false, delete: false };
        } else {
          const current = next[m.id] || { view: false, create: false, edit: false, delete: false };
          const newValue = !current[action];
          next[m.id] = {
            ...current,
            [action]: newValue,
            view: (newValue && action !== 'view') ? true : (action === 'view' ? newValue : current.view)
          };
        }
      });
      return next;
    });
  };

  const filteredUsers = users.filter(u => 
    u.name.toLowerCase().includes(search.toLowerCase()) ||
    u.email.toLowerCase().includes(search.toLowerCase()) ||
    u.role.toLowerCase().includes(search.toLowerCase())
  );

  const handleAddUser = (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);

    const cleanName = name.trim();
    const cleanEmail = email.trim().toLowerCase();

    if (!cleanName || !cleanEmail) {
      setError('Please provide full name and email address.');
      return;
    }

    if (users.some(u => u.email.toLowerCase() === cleanEmail)) {
      setError('A staff user account with this email address already exists.');
      return;
    }

    if (!password || password.length < 4) {
      setError('Please enter a password of at least 4 characters for staff sign-in.');
      return;
    }

    const allowedMods = Object.keys(staffModulePermissions).filter(m => staffModulePermissions[m]?.view);

    const newUser: User = {
      id: 'usr_' + Math.random().toString(36).substr(2, 9),
      name: cleanName,
      email: cleanEmail,
      password: password,
      role,
      disabled,
      permissions: {
        create: staffModulePermissions.inventory?.create || staffModulePermissions.sales?.create || false,
        edit: staffModulePermissions.inventory?.edit || staffModulePermissions.sales?.edit || false,
        delete: staffModulePermissions.inventory?.delete || staffModulePermissions.sales?.delete || false,
        stockIn: staffModulePermissions.inventory?.create || false,
        stockOut: staffModulePermissions.inventory?.edit || false,
        allowedModules: allowedMods,
        modulePermissions: staffModulePermissions
      },
      createdAt: new Date().toISOString()
    };

    const updated = [...users, newUser];
    storage.saveUsers(updated);
    setUsers(updated);
    setIsAddUserOpen(false);
    resetStaffForm();

    storage.logActivity({
      type: 'product-created',
      description: `Created staff account: ${newUser.name} (${newUser.email}) as ${newUser.role.toUpperCase()} with ${allowedMods.length} accessible modules.`,
      userId: currentUser.id,
      userName: currentUser.name
    });
  };

  const startEditUser = (user: User) => {
    setEditingUser(user);
    setName(user.name);
    setEmail(user.email);
    setPassword(user.password || '');
    setShowPassword(false);
    setRole(user.role);
    setDisabled(!!user.disabled);

    // Populate module permissions from user or role default
    if (user.permissions?.modulePermissions) {
      setStaffModulePermissions(JSON.parse(JSON.stringify(user.permissions.modulePermissions)));
    } else {
      const cfg = getRoleConfig(user.role);
      setStaffModulePermissions(cfg.modulePermissions || createEmptyModulePermissions());
    }

    setIsEditUserOpen(true);
  };

  const handleEditUser = (e: React.FormEvent) => {
    e.preventDefault();
    if (!editingUser) return;
    setError(null);

    const cleanName = name.trim();
    const cleanEmail = email.trim().toLowerCase();

    if (!cleanName || !cleanEmail) {
      setError('Please fill in all required fields.');
      return;
    }

    if (users.some(u => u.id !== editingUser.id && u.email.toLowerCase() === cleanEmail)) {
      setError('A user account with this email address already exists.');
      return;
    }

    const allowedMods = Object.keys(staffModulePermissions).filter(m => staffModulePermissions[m]?.view);

    const updatedUser: User = {
      ...editingUser,
      name: cleanName,
      email: cleanEmail,
      password: password || editingUser.password,
      role,
      disabled,
      permissions: {
        create: staffModulePermissions.inventory?.create || staffModulePermissions.sales?.create || false,
        edit: staffModulePermissions.inventory?.edit || staffModulePermissions.sales?.edit || false,
        delete: staffModulePermissions.inventory?.delete || staffModulePermissions.sales?.delete || false,
        stockIn: staffModulePermissions.inventory?.create || false,
        stockOut: staffModulePermissions.inventory?.edit || false,
        allowedModules: allowedMods,
        modulePermissions: staffModulePermissions
      }
    };

    const updated = users.map(u => u.id === editingUser.id ? updatedUser : u);
    storage.saveUsers(updated);
    setUsers(updated);
    setIsEditUserOpen(false);
    setEditingUser(null);
    resetStaffForm();

    storage.logActivity({
      type: 'product-created',
      description: `Updated staff permissions for: ${updatedUser.name} (${updatedUser.role.toUpperCase()}).`,
      userId: currentUser.id,
      userName: currentUser.name
    });
  };

  const handleDeleteUser = (id: string) => {
    if (id === currentUser.id || id === 'demo-admin-id') {
      alert("You cannot delete your own session or the default Administrator account!");
      return;
    }

    if (!confirm('Are you absolutely sure you want to permanently delete this user account?')) return;

    const userToDelete = users.find(u => u.id === id);
    const updated = users.filter(u => u.id !== id);
    storage.saveUsers(updated);
    setUsers(updated);

    if (userToDelete) {
      storage.logActivity({
        type: 'product-created',
        description: `Deleted staff account: ${userToDelete.name} (${userToDelete.email})`,
        userId: currentUser.id,
        userName: currentUser.name
      });
    }
  };

  // Role Customization logic
  const openNewRoleModal = () => {
    setEditingRole(null);
    setRoleLabel('');
    setRoleDesc('');
    setRoleBadgeBg('bg-indigo-100');
    setRoleBadgeText('text-indigo-800');
    setRoleBadgeBorder('border-indigo-200');
    setRoleModulePermissions(createEmptyModulePermissions());
    setIsRoleModalOpen(true);
  };

  const startEditRole = (roleConfig: RoleConfig) => {
    setEditingRole(roleConfig);
    setRoleLabel(roleConfig.label);
    setRoleDesc(roleConfig.description);
    setRoleBadgeBg(roleConfig.badgeBg || 'bg-indigo-100');
    setRoleBadgeText(roleConfig.badgeText || 'text-indigo-800');
    setRoleBadgeBorder(roleConfig.badgeBorder || 'border-indigo-200');
    setRoleModulePermissions(roleConfig.modulePermissions ? JSON.parse(JSON.stringify(roleConfig.modulePermissions)) : createEmptyModulePermissions());
    setIsRoleModalOpen(true);
  };

  const toggleRoleModuleAction = (moduleId: string, action: 'view' | 'create' | 'edit' | 'delete') => {
    setRoleModulePermissions(prev => {
      const next = { ...prev };
      const currentMod = next[moduleId] || { view: false, create: false, edit: false, delete: false };
      const updatedValue = !currentMod[action];

      let updatedView = currentMod.view;
      if (updatedValue && (action === 'create' || action === 'edit' || action === 'delete')) {
        updatedView = true;
      }
      let updatedCreate = currentMod.create;
      let updatedEdit = currentMod.edit;
      let updatedDelete = currentMod.delete;
      if (action === 'view' && !updatedValue) {
        updatedCreate = false;
        updatedEdit = false;
        updatedDelete = false;
      } else if (action !== 'view') {
        if (action === 'create') updatedCreate = updatedValue;
        if (action === 'edit') updatedEdit = updatedValue;
        if (action === 'delete') updatedDelete = updatedValue;
      }

      next[moduleId] = {
        view: updatedView,
        create: updatedCreate,
        edit: updatedEdit,
        delete: updatedDelete
      };
      return next;
    });
  };

  const batchUpdateRolePermissions = (action: 'view' | 'create' | 'edit' | 'delete' | 'all' | 'clear') => {
    setRoleModulePermissions(prev => {
      const next = { ...prev };
      ALL_SYSTEM_MODULES.forEach(m => {
        if (action === 'all') {
          next[m.id] = { view: true, create: true, edit: true, delete: true };
        } else if (action === 'clear') {
          next[m.id] = { view: false, create: false, edit: false, delete: false };
        } else {
          const current = next[m.id] || { view: false, create: false, edit: false, delete: false };
          const newValue = !current[action];
          next[m.id] = {
            ...current,
            [action]: newValue,
            view: (newValue && action !== 'view') ? true : (action === 'view' ? newValue : current.view)
          };
        }
      });
      return next;
    });
  };

  const handleSaveRole = (e: React.FormEvent) => {
    e.preventDefault();
    if (!roleLabel.trim()) {
      alert("Please provide a role title.");
      return;
    }

    const customRoles = storage.getCustomRoles() || [];
    const roleId = editingRole ? editingRole.id : 'role_' + roleLabel.toLowerCase().replace(/[^a-z0-9]/g, '_');
    const allowedMods = Object.keys(roleModulePermissions).filter(m => roleModulePermissions[m]?.view);

    const newRoleConfig: RoleConfig = {
      id: roleId,
      label: roleLabel.trim(),
      description: roleDesc.trim() || 'Custom staff role definition',
      badgeBg: roleBadgeBg,
      badgeText: roleBadgeText,
      badgeBorder: roleBadgeBorder,
      isSystem: editingRole ? editingRole.isSystem : false,
      modulePermissions: roleModulePermissions,
      allowedModules: allowedMods
    };

    const updatedRoles = customRoles.filter(r => r.id !== roleId);
    updatedRoles.push(newRoleConfig);

    storage.saveCustomRoles(updatedRoles);
    refreshRoles();
    setIsRoleModalOpen(false);

    storage.logActivity({
      type: 'product-created',
      description: `Saved role definition: ${newRoleConfig.label} (${newRoleConfig.id}).`,
      userId: currentUser.id,
      userName: currentUser.name
    });
  };

  const handleDeleteRole = (roleId: string) => {
    const roleToDelete = roles.find(r => r.id === roleId);
    if (roleToDelete?.isSystem) {
      alert("Default system roles cannot be completely deleted, but you can customize their permission settings.");
      return;
    }

    if (!confirm(`Are you sure you want to delete custom role "${roleToDelete?.label}"?`)) return;

    const customRoles = storage.getCustomRoles() || [];
    const updated = customRoles.filter(r => r.id !== roleId);
    storage.saveCustomRoles(updated);
    refreshRoles();

    storage.logActivity({
      type: 'product-created',
      description: `Deleted custom role: ${roleToDelete?.label}`,
      userId: currentUser.id,
      userName: currentUser.name
    });
  };

  // Stats Counters
  const totalStaff = users.length;
  const activeStaff = users.filter(u => !u.disabled).length;
  const disabledStaff = users.filter(u => u.disabled).length;
  const adminCount = users.filter(u => u.role === 'admin').length;

  return (
    <div className="space-y-6">
      {/* Top Header & Navigation Tabs */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 className="text-2xl font-black text-slate-900 tracking-tight flex items-center gap-2">
            <Users2 className="text-primary-theme" size={26} />
            User & Access Control Center
          </h2>
          <p className="text-slate-500 text-xs mt-0.5">
            Create staff accounts, define custom roles, and configure view, create, edit, and delete permissions for every module.
          </p>
        </div>

        {/* Tab Switcher */}
        <div className="flex bg-slate-100 p-1 rounded-2xl border border-slate-200 shrink-0">
          <button
            onClick={() => setActiveTab('users')}
            className={`px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 cursor-pointer ${
              activeTab === 'users'
                ? 'bg-white text-slate-900 shadow-sm border border-slate-200/80'
                : 'text-slate-500 hover:text-slate-800'
            }`}
          >
            <UsersIcon size={15} />
            Staff Accounts ({users.length})
          </button>
          <button
            onClick={() => setActiveTab('roles')}
            className={`px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 cursor-pointer ${
              activeTab === 'roles'
                ? 'bg-white text-slate-900 shadow-sm border border-slate-200/80'
                : 'text-slate-500 hover:text-slate-800'
            }`}
          >
            <SlidersHorizontal size={15} />
            Roles & Custom Permissions ({roles.length})
          </button>
        </div>
      </div>

      {/* Stats Summary Bar */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div className="bg-card-theme-bg p-4 rounded-2xl border border-slate-200/80 shadow-sm">
          <span className="text-[10px] font-bold uppercase text-slate-400 tracking-wider">Total Staff</span>
          <div className="text-2xl font-black text-slate-900 mt-1">{totalStaff}</div>
        </div>
        <div className="bg-card-theme-bg p-4 rounded-2xl border border-slate-200/80 shadow-sm">
          <span className="text-[10px] font-bold uppercase text-emerald-600 tracking-wider">Active Users</span>
          <div className="text-2xl font-black text-emerald-600 mt-1">{activeStaff}</div>
        </div>
        <div className="bg-card-theme-bg p-4 rounded-2xl border border-slate-200/80 shadow-sm">
          <span className="text-[10px] font-bold uppercase text-rose-500 tracking-wider">Disabled</span>
          <div className="text-2xl font-black text-rose-600 mt-1">{disabledStaff}</div>
        </div>
        <div className="bg-card-theme-bg p-4 rounded-2xl border border-slate-200/80 shadow-sm">
          <span className="text-[10px] font-bold uppercase text-purple-600 tracking-wider">Configured Roles</span>
          <div className="text-2xl font-black text-purple-700 mt-1">{roles.length}</div>
        </div>
      </div>

      {/* TAB 1: STAFF ACCOUNTS */}
      {activeTab === 'users' && (
        <div className="space-y-4">
          <div className="flex flex-col sm:flex-row gap-3 items-center justify-between">
            <div className="relative w-full sm:w-80">
              <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" size={16} />
              <input 
                type="text"
                placeholder="Search staff by name, email, or role..."
                className="pl-10 pr-4 py-2 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-theme w-full bg-card-theme-bg text-xs font-medium"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
              />
            </div>
            {canCreateUser && (
              <button 
                onClick={() => { resetStaffForm(); setIsAddUserOpen(true); }}
                className="bg-primary-theme hover:bg-primary-theme-hover text-white px-4 py-2.5 rounded-xl font-bold text-xs flex items-center gap-2 transition-all shadow-md shadow-primary-theme-light cursor-pointer w-full sm:w-auto justify-center"
              >
                <Plus size={16} />
                Create Staff Account
              </button>
            )}
          </div>

          <div className="bg-card-theme-bg rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
            <div className="p-4 border-b border-slate-100 flex items-center justify-between">
              <h3 className="text-xs font-black uppercase tracking-wider text-slate-700 flex items-center gap-2">
                <Shield size={16} className="text-indigo-600" />
                Staff Members List
              </h3>
              <span className="text-[11px] text-slate-400 font-medium">
                Showing {filteredUsers.length} of {users.length} accounts
              </span>
            </div>

            <div className="overflow-x-auto">
              <table className="w-full text-left border-collapse">
                <thead>
                  <tr className="bg-layout-theme-bg border-b border-slate-200 text-[10px] uppercase font-bold text-slate-500 tracking-wider">
                    <th className="px-6 py-3.5">Staff Member</th>
                    <th className="px-6 py-3.5">Role</th>
                    <th className="px-6 py-3.5">Status</th>
                    <th className="px-6 py-3.5">Accessible Modules</th>
                    <th className="px-6 py-3.5 text-center">CRUD Capabilities</th>
                    <th className="px-6 py-3.5 text-right">Manage</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {filteredUsers.length === 0 ? (
                    <tr>
                      <td colSpan={6} className="px-6 py-12 text-center text-slate-400 text-xs font-medium">
                        No staff accounts match your search query.
                      </td>
                    </tr>
                  ) : (
                    filteredUsers.map((u) => {
                      const userRoleConfig = getRoleConfig(u.role);
                      const modPerms: ModulePermissionsMap = u.permissions?.modulePermissions || userRoleConfig.modulePermissions || createEmptyModulePermissions();
                      
                      const accessibleModulesCount = ALL_SYSTEM_MODULES.filter(m => u.role === 'admin' || modPerms[m.id]?.view || u.permissions?.allowedModules?.includes(m.id)).length;

                      // Calculate CRUD totals
                      const canCreateAny = u.role === 'admin' || ALL_SYSTEM_MODULES.some(m => modPerms[m.id]?.create);
                      const canEditAny = u.role === 'admin' || ALL_SYSTEM_MODULES.some(m => modPerms[m.id]?.edit);
                      const canDeleteAny = u.role === 'admin' || ALL_SYSTEM_MODULES.some(m => modPerms[m.id]?.delete);

                      return (
                        <tr key={u.id} className="hover:bg-layout-theme-bg transition-colors">
                          <td className="px-6 py-4">
                            <div className="flex items-center gap-3">
                              <div className="w-9 h-9 bg-slate-100 rounded-xl flex items-center justify-center text-slate-700 font-bold uppercase border border-slate-200/60 shrink-0">
                                {u.name.charAt(0)}
                              </div>
                              <div>
                                <div className="font-bold text-slate-900 text-xs flex items-center gap-1.5">
                                  {u.name}
                                  {u.id === currentUser.id && (
                                    <span className="text-[9px] bg-primary-theme-light text-primary-theme-hover font-bold uppercase px-1.5 py-0.2 rounded border border-primary-theme-light">You</span>
                                  )}
                                </div>
                                <div className="text-[11px] text-slate-400 font-mono flex items-center gap-1 mt-0.5">
                                  <Mail size={11} />
                                  {u.email}
                                </div>
                              </div>
                            </div>
                          </td>

                          <td className="px-6 py-4">
                            <span className={`inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold border ${userRoleConfig.badgeBg || 'bg-indigo-100'} ${userRoleConfig.badgeText || 'text-indigo-800'} ${userRoleConfig.badgeBorder || 'border-indigo-200'}`}>
                              <Shield size={12} />
                              {userRoleConfig.label || u.role.toUpperCase()}
                            </span>
                          </td>

                          <td className="px-6 py-4">
                            {u.disabled ? (
                              <span className="inline-flex items-center gap-1 px-2.5 py-0.5 bg-rose-50 text-rose-700 border border-rose-200 rounded-full text-[10px] font-bold uppercase">
                                <UserX size={12} />
                                Disabled
                              </span>
                            ) : (
                              <span className="inline-flex items-center gap-1 px-2.5 py-0.5 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-full text-[10px] font-bold uppercase">
                                <UserCheck size={12} />
                                Active
                              </span>
                            )}
                          </td>

                          <td className="px-6 py-4">
                            <span className="text-xs font-bold text-slate-800 font-mono">
                              {accessibleModulesCount} / {ALL_SYSTEM_MODULES.length}
                            </span>
                            <span className="text-[10px] text-slate-400 block">Modules Authorized</span>
                          </td>

                          <td className="px-6 py-4 text-center">
                            <div className="inline-flex items-center gap-1">
                              <span className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase border ${
                                u.role === 'admin' || ALL_SYSTEM_MODULES.some(m => modPerms[m.id]?.view)
                                  ? 'bg-blue-50 border-blue-200 text-blue-700' 
                                  : 'bg-slate-100 border-slate-200 text-slate-400 opacity-50'
                              }`}>View</span>
                              <span className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase border ${
                                canCreateAny 
                                  ? 'bg-emerald-50 border-emerald-200 text-emerald-700' 
                                  : 'bg-slate-100 border-slate-200 text-slate-400 opacity-50'
                              }`}>Create</span>
                              <span className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase border ${
                                canEditAny 
                                  ? 'bg-indigo-50 border-indigo-200 text-indigo-700' 
                                  : 'bg-slate-100 border-slate-200 text-slate-400 opacity-50'
                              }`}>Edit</span>
                              <span className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase border ${
                                canDeleteAny 
                                  ? 'bg-rose-50 border-rose-200 text-rose-700' 
                                  : 'bg-slate-100 border-slate-200 text-slate-400 opacity-50'
                              }`}>Delete</span>
                            </div>
                          </td>

                          <td className="px-6 py-4 text-right">
                            <div className="flex items-center justify-end gap-1">
                              {canEditUser && (
                                <button 
                                  onClick={() => startEditUser(u)}
                                  className="p-2 text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors cursor-pointer flex items-center gap-1 text-xs font-bold"
                                >
                                  <Edit2 size={15} />
                                  <span>Edit Permissions</span>
                                </button>
                              )}
                              {canDeleteUser && u.id !== currentUser.id && u.id !== 'demo-admin-id' && (
                                <button 
                                  onClick={() => handleDeleteUser(u.id)}
                                  className="p-2 text-rose-600 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer"
                                  title="Delete staff account"
                                >
                                  <Trash2 size={15} />
                                </button>
                              )}
                            </div>
                          </td>
                        </tr>
                      );
                    })
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* TAB 2: ROLES & CUSTOM PERMISSIONS MANAGEMENT */}
      {activeTab === 'roles' && (
        <div className="space-y-4">
          <div className="flex flex-col sm:flex-row gap-3 items-center justify-between">
            <p className="text-xs text-slate-500 font-medium">
              System roles serve as access templates. Customize permissions for existing roles or create custom staff roles.
            </p>
            <button 
              onClick={openNewRoleModal}
              className="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl font-bold text-xs flex items-center gap-2 transition-all shadow-md shadow-indigo-100 cursor-pointer"
            >
              <PlusCircle size={16} />
              Add Custom Role
            </button>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {roles.map((r) => {
              const modPerms = r.modulePermissions || createEmptyModulePermissions();
              const viewCount = ALL_SYSTEM_MODULES.filter(m => r.id === 'admin' || modPerms[m.id]?.view).length;
              const createCount = ALL_SYSTEM_MODULES.filter(m => r.id === 'admin' || modPerms[m.id]?.create).length;
              const editCount = ALL_SYSTEM_MODULES.filter(m => r.id === 'admin' || modPerms[m.id]?.edit).length;
              const deleteCount = ALL_SYSTEM_MODULES.filter(m => r.id === 'admin' || modPerms[m.id]?.delete).length;

              return (
                <div key={r.id} className="bg-card-theme-bg rounded-2xl border border-slate-200 p-5 shadow-sm space-y-4 flex flex-col justify-between">
                  <div>
                    <div className="flex items-start justify-between gap-3">
                      <div>
                        <div className="flex items-center gap-2">
                          <span className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black border ${r.badgeBg || 'bg-indigo-100'} ${r.badgeText || 'text-indigo-800'} ${r.badgeBorder || 'border-indigo-200'}`}>
                            <Shield size={14} />
                            {r.label}
                          </span>
                          {r.isSystem ? (
                            <span className="text-[10px] bg-slate-100 text-slate-500 px-2 py-0.5 rounded font-mono font-bold uppercase">System Role</span>
                          ) : (
                            <span className="text-[10px] bg-purple-100 text-purple-700 px-2 py-0.5 rounded font-mono font-bold uppercase">Custom Role</span>
                          )}
                        </div>
                        <p className="text-xs text-slate-500 font-medium mt-2 leading-relaxed">
                          {r.description}
                        </p>
                      </div>

                      <div className="flex items-center gap-1 shrink-0">
                        <button 
                          onClick={() => startEditRole(r)}
                          className="p-2 text-indigo-600 hover:bg-indigo-50 rounded-xl transition-colors cursor-pointer flex items-center gap-1 text-xs font-bold border border-indigo-100"
                        >
                          <Edit2 size={14} />
                          <span>Customize</span>
                        </button>
                        {!r.isSystem && (
                          <button 
                            onClick={() => handleDeleteRole(r.id)}
                            className="p-2 text-rose-600 hover:bg-rose-50 rounded-xl transition-colors cursor-pointer border border-rose-100"
                            title="Delete custom role"
                          >
                            <Trash2 size={14} />
                          </button>
                        )}
                      </div>
                    </div>

                    {/* CRUD Capabilities Summary Matrix */}
                    <div className="grid grid-cols-4 gap-2 mt-4 pt-3 border-t border-slate-100 text-center">
                      <div className="bg-blue-50/70 p-2 rounded-xl border border-blue-100">
                        <span className="text-[9px] uppercase font-bold text-blue-700 block">VIEW</span>
                        <span className="text-sm font-black text-blue-900 mt-0.5 block">{viewCount} / {ALL_SYSTEM_MODULES.length}</span>
                      </div>
                      <div className="bg-emerald-50/70 p-2 rounded-xl border border-emerald-100">
                        <span className="text-[9px] uppercase font-bold text-emerald-700 block">CREATE</span>
                        <span className="text-sm font-black text-emerald-900 mt-0.5 block">{createCount} / {ALL_SYSTEM_MODULES.length}</span>
                      </div>
                      <div className="bg-indigo-50/70 p-2 rounded-xl border border-indigo-100">
                        <span className="text-[9px] uppercase font-bold text-indigo-700 block">EDIT</span>
                        <span className="text-sm font-black text-indigo-900 mt-0.5 block">{editCount} / {ALL_SYSTEM_MODULES.length}</span>
                      </div>
                      <div className="bg-rose-50/70 p-2 rounded-xl border border-rose-100">
                        <span className="text-[9px] uppercase font-bold text-rose-700 block">DELETE</span>
                        <span className="text-sm font-black text-rose-900 mt-0.5 block">{deleteCount} / {ALL_SYSTEM_MODULES.length}</span>
                      </div>
                    </div>
                  </div>

                  {/* Accessible Module Badges */}
                  <div className="pt-2">
                    <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">
                      Module Access:
                    </span>
                    <div className="flex flex-wrap gap-1">
                      {ALL_SYSTEM_MODULES.map(m => {
                        const canView = r.id === 'admin' || modPerms[m.id]?.view;
                        if (!canView) return null;
                        const IconComp = MODULE_ICONS[m.id] || Package;

                        return (
                          <span key={m.id} className="inline-flex items-center gap-1 px-2 py-0.5 bg-slate-100 border border-slate-200 text-slate-700 rounded text-[10px] font-semibold">
                            <IconComp size={11} className="text-indigo-600" />
                            {m.label.split(' ')[0]}
                          </span>
                        );
                      })}
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* MODAL 1: CREATE / EDIT STAFF ACCOUNT */}
      {(isAddUserOpen || isEditUserOpen) && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
          <form 
            onSubmit={isAddUserOpen ? handleAddUser : handleEditUser} 
            className="bg-card-theme-bg rounded-2xl p-6 max-w-3xl w-full shadow-2xl space-y-5 overflow-y-auto max-h-[92vh] border border-slate-200"
          >
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <div>
                <h3 className="text-lg font-black text-slate-900 tracking-tight">
                  {isAddUserOpen ? 'Create New Staff Account' : 'Manage Staff Access & Permissions'}
                </h3>
                <p className="text-xs text-slate-400 mt-0.5">
                  Set user credentials, select a role, and customize granular View, Create, Edit, and Delete permissions.
                </p>
              </div>
              <button 
                type="button" 
                onClick={() => { setIsAddUserOpen(false); setIsEditUserOpen(false); resetStaffForm(); }}
                className="p-1 text-slate-400 hover:text-slate-600 rounded-lg transition-colors cursor-pointer"
              >
                <X size={20} />
              </button>
            </div>

            {error && (
              <div className="p-3 bg-rose-50 border border-rose-200 text-rose-800 text-xs rounded-xl font-medium leading-relaxed flex items-center gap-2">
                <ShieldAlert size={16} className="shrink-0 text-rose-600" />
                {error}
              </div>
            )}

            {/* Account Details */}
            <div className="space-y-3 bg-slate-50/70 p-4 rounded-xl border border-slate-200/80">
              <span className="text-[10px] font-black uppercase text-indigo-700 tracking-wider block mb-1">
                1. Account Credentials
              </span>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-bold text-slate-700 mb-1">Full Name *</label>
                  <div className="relative">
                    <UserIcon className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={15} />
                    <input 
                      type="text"
                      required
                      placeholder="e.g. Sarah Connor"
                      className="w-full pl-9 pr-3 py-2 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none text-xs font-medium bg-white"
                      value={name}
                      onChange={(e) => setName(e.target.value)}
                    />
                  </div>
                </div>

                <div>
                  <label className="block text-xs font-bold text-slate-700 mb-1">Email Address *</label>
                  <div className="relative">
                    <Mail className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={15} />
                    <input 
                      type="email"
                      required
                      placeholder="e.g. sarah@hysam.com"
                      className="w-full pl-9 pr-3 py-2 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none text-xs font-medium bg-white"
                      value={email}
                      onChange={(e) => setEmail(e.target.value)}
                    />
                  </div>
                </div>
              </div>

              <div>
                <div className="flex items-center justify-between mb-1">
                  <label className="block text-xs font-bold text-slate-700">
                    Login Password {isAddUserOpen && '*'}
                  </label>
                  <button
                    type="button"
                    onClick={generateRandomPassword}
                    className="text-[10px] font-bold text-indigo-600 hover:text-indigo-800 flex items-center gap-1 cursor-pointer"
                  >
                    <Sparkles size={12} />
                    Auto-Generate Password
                  </button>
                </div>
                <div className="relative">
                  <Key className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={15} />
                  <input 
                    type={showPassword ? 'text' : 'password'}
                    required={isAddUserOpen}
                    placeholder={isAddUserOpen ? 'Enter staff password...' : 'Leave blank to keep existing password'}
                    className="w-full pl-9 pr-10 py-2 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none text-xs font-medium bg-white"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword(!showPassword)}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer"
                  >
                    {showPassword ? <EyeOff size={15} /> : <Eye size={15} />}
                  </button>
                </div>
              </div>
            </div>

            {/* Role Preset Selection */}
            <div className="space-y-2">
              <label className="block text-xs font-bold text-slate-700">2. Select Primary Role Template</label>
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
                {roles.map((r) => {
                  const isSelected = role === r.id;
                  return (
                    <button
                      key={r.id}
                      type="button"
                      onClick={() => handleRoleSelectForStaff(r.id)}
                      className={`p-2.5 rounded-xl border text-center transition-all cursor-pointer ${
                        isSelected 
                          ? 'border-indigo-600 bg-indigo-50/90 text-indigo-950 font-bold shadow-sm' 
                          : 'border-slate-200 bg-white hover:border-slate-300 text-slate-600'
                      }`}
                    >
                      <span className="block text-xs uppercase font-extrabold tracking-wider">{r.label}</span>
                      <span className="block text-[9px] text-slate-400 font-medium mt-0.5 truncate">
                        {r.isSystem ? 'System Preset' : 'Custom Role'}
                      </span>
                    </button>
                  );
                })}
              </div>
            </div>

            {/* GRANULAR CRUD PERMISSIONS MATRIX */}
            <div className="space-y-3 bg-indigo-50/40 p-4 rounded-xl border border-indigo-100">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-indigo-100 pb-2">
                <div>
                  <span className="text-[10px] font-black uppercase text-indigo-900 tracking-wider block">
                    3. Granular Module Permissions Matrix (CRUD)
                  </span>
                  <p className="text-[11px] text-slate-500 font-medium">
                    Specify exact View, Create, Edit, and Delete rights per module for this user.
                  </p>
                </div>

                {role !== 'admin' && (
                  <div className="flex flex-wrap gap-1.5 text-[10px] font-bold">
                    <button type="button" onClick={() => batchUpdateStaffPermissions('all')} className="px-2 py-0.5 bg-indigo-600 text-white rounded hover:bg-indigo-700 transition-colors cursor-pointer">Enable All</button>
                    <button type="button" onClick={() => batchUpdateStaffPermissions('view')} className="px-2 py-0.5 bg-blue-100 text-blue-800 rounded hover:bg-blue-200 transition-colors cursor-pointer">Toggle View</button>
                    <button type="button" onClick={() => batchUpdateStaffPermissions('create')} className="px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded hover:bg-emerald-200 transition-colors cursor-pointer">Toggle Create</button>
                    <button type="button" onClick={() => batchUpdateStaffPermissions('edit')} className="px-2 py-0.5 bg-indigo-100 text-indigo-800 rounded hover:bg-indigo-200 transition-colors cursor-pointer">Toggle Edit</button>
                    <button type="button" onClick={() => batchUpdateStaffPermissions('delete')} className="px-2 py-0.5 bg-rose-100 text-rose-800 rounded hover:bg-rose-200 transition-colors cursor-pointer">Toggle Delete</button>
                    <button type="button" onClick={() => batchUpdateStaffPermissions('clear')} className="px-2 py-0.5 bg-slate-200 text-slate-700 rounded hover:bg-slate-300 transition-colors cursor-pointer">Clear All</button>
                  </div>
                )}
              </div>

              {role === 'admin' ? (
                <div className="p-3 bg-white rounded-xl border border-indigo-200 text-xs text-indigo-900 font-medium leading-relaxed flex items-center gap-2">
                  <Shield size={16} className="text-purple-600 shrink-0" />
                  <span>Administrators possess full View, Create, Edit, and Delete access across all modules.</span>
                </div>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full text-left border-collapse">
                    <thead>
                      <tr className="border-b border-indigo-100 text-[10px] uppercase font-bold text-slate-500">
                        <th className="py-2 px-2">Module / Feature</th>
                        <th className="py-2 px-2 text-center text-blue-700">View</th>
                        <th className="py-2 px-2 text-center text-emerald-700">Create</th>
                        <th className="py-2 px-2 text-center text-indigo-700">Edit</th>
                        <th className="py-2 px-2 text-center text-rose-700">Delete</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-indigo-100/60">
                      {ALL_SYSTEM_MODULES.map((m) => {
                        const perm = staffModulePermissions[m.id] || { view: false, create: false, edit: false, delete: false };
                        const IconComp = MODULE_ICONS[m.id] || Package;

                        return (
                          <tr key={m.id} className="hover:bg-indigo-50/50 transition-colors">
                            <td className="py-2 px-2">
                              <div className="flex items-center gap-2">
                                <IconComp size={15} className="text-indigo-600 shrink-0" />
                                <div>
                                  <span className="text-xs font-bold text-slate-800 block leading-tight">{m.label}</span>
                                  <span className="text-[10px] text-slate-400 font-medium">{m.desc}</span>
                                </div>
                              </div>
                            </td>

                            {/* View Checkbox */}
                            <td className="py-2 px-2 text-center">
                              <button
                                type="button"
                                onClick={() => toggleStaffModuleAction(m.id, 'view')}
                                className={`p-1.5 rounded-lg border transition-all cursor-pointer ${
                                  perm.view ? 'bg-blue-600 border-blue-600 text-white' : 'bg-white border-slate-300 text-slate-300 hover:border-slate-400'
                                }`}
                              >
                                <Check size={14} />
                              </button>
                            </td>

                            {/* Create Checkbox */}
                            <td className="py-2 px-2 text-center">
                              <button
                                type="button"
                                onClick={() => toggleStaffModuleAction(m.id, 'create')}
                                className={`p-1.5 rounded-lg border transition-all cursor-pointer ${
                                  perm.create ? 'bg-emerald-600 border-emerald-600 text-white' : 'bg-white border-slate-300 text-slate-300 hover:border-slate-400'
                                }`}
                              >
                                <Check size={14} />
                              </button>
                            </td>

                            {/* Edit Checkbox */}
                            <td className="py-2 px-2 text-center">
                              <button
                                type="button"
                                onClick={() => toggleStaffModuleAction(m.id, 'edit')}
                                className={`p-1.5 rounded-lg border transition-all cursor-pointer ${
                                  perm.edit ? 'bg-indigo-600 border-indigo-600 text-white' : 'bg-white border-slate-300 text-slate-300 hover:border-slate-400'
                                }`}
                              >
                                <Check size={14} />
                              </button>
                            </td>

                            {/* Delete Checkbox */}
                            <td className="py-2 px-2 text-center">
                              <button
                                type="button"
                                onClick={() => toggleStaffModuleAction(m.id, 'delete')}
                                className={`p-1.5 rounded-lg border transition-all cursor-pointer ${
                                  perm.delete ? 'bg-rose-600 border-rose-600 text-white' : 'bg-white border-slate-300 text-slate-300 hover:border-slate-400'
                                }`}
                              >
                                <Check size={14} />
                              </button>
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              )}
            </div>

            {/* Account Active Toggle */}
            {isEditUserOpen && editingUser && editingUser.id !== currentUser.id && (
              <div className="pt-2 border-t border-slate-100 flex items-center justify-between">
                <span className="text-xs font-bold text-slate-700">Account Status</span>
                <div className="flex gap-2">
                  <button
                    type="button"
                    onClick={() => setDisabled(false)}
                    className={`px-3 py-1.5 rounded-xl border text-center text-xs font-bold cursor-pointer transition-colors ${
                      !disabled 
                        ? 'border-emerald-500 bg-emerald-50 text-emerald-800' 
                        : 'border-slate-200 bg-slate-50 text-slate-400'
                    }`}
                  >
                    Active
                  </button>
                  <button
                    type="button"
                    onClick={() => setDisabled(true)}
                    className={`px-3 py-1.5 rounded-xl border text-center text-xs font-bold cursor-pointer transition-colors ${
                      disabled 
                        ? 'border-rose-500 bg-rose-50 text-rose-800' 
                        : 'border-slate-200 bg-slate-50 text-slate-400'
                    }`}
                  >
                    Disabled
                  </button>
                </div>
              </div>
            )}

            {/* Modal Footer Buttons */}
            <div className="flex gap-3 pt-3 border-t border-slate-100">
              <button 
                type="button"
                onClick={() => { setIsAddUserOpen(false); setIsEditUserOpen(false); resetStaffForm(); }}
                className="flex-1 py-2.5 border border-slate-200 text-slate-600 rounded-xl hover:bg-slate-50 transition-colors text-xs font-bold cursor-pointer"
              >
                Cancel
              </button>
              <button 
                type="submit"
                className="flex-1 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-lg shadow-indigo-100 transition-all cursor-pointer"
              >
                {isAddUserOpen ? 'Create Staff Account' : 'Save Changes'}
              </button>
            </div>
          </form>
        </div>
      )}

      {/* MODAL 2: CUSTOMIZE ROLE / ADD CUSTOM ROLE */}
      {isRoleModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
          <form 
            onSubmit={handleSaveRole}
            className="bg-card-theme-bg rounded-2xl p-6 max-w-3xl w-full shadow-2xl space-y-5 overflow-y-auto max-h-[92vh] border border-slate-200"
          >
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <div>
                <h3 className="text-lg font-black text-slate-900 tracking-tight">
                  {editingRole ? `Customize Role: ${editingRole.label}` : 'Create New Custom Role'}
                </h3>
                <p className="text-xs text-slate-400 mt-0.5">
                  Define default View, Create, Edit, and Delete access rights for this role template.
                </p>
              </div>
              <button 
                type="button" 
                onClick={() => setIsRoleModalOpen(false)}
                className="p-1 text-slate-400 hover:text-slate-600 rounded-lg transition-colors cursor-pointer"
              >
                <X size={20} />
              </button>
            </div>

            {/* Role Header Info */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 bg-slate-50 p-4 rounded-xl border border-slate-200">
              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">Role Title / Name *</label>
                <input 
                  type="text"
                  required
                  placeholder="e.g. Branch Supervisor, Cashier Lead..."
                  className="w-full px-3 py-2 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none text-xs font-bold bg-white"
                  value={roleLabel}
                  onChange={(e) => setRoleLabel(e.target.value)}
                />
              </div>

              <div>
                <label className="block text-xs font-bold text-slate-700 mb-1">Badge Styling</label>
                <div className="flex gap-2">
                  <button
                    type="button"
                    onClick={() => { setRoleBadgeBg('bg-indigo-100'); setRoleBadgeText('text-indigo-800'); setRoleBadgeBorder('border-indigo-200'); }}
                    className="flex-1 py-2 rounded-lg bg-indigo-100 text-indigo-800 border border-indigo-200 text-[10px] font-bold cursor-pointer"
                  >Indigo</button>
                  <button
                    type="button"
                    onClick={() => { setRoleBadgeBg('bg-emerald-100'); setRoleBadgeText('text-emerald-800'); setRoleBadgeBorder('border-emerald-200'); }}
                    className="flex-1 py-2 rounded-lg bg-emerald-100 text-emerald-800 border border-emerald-200 text-[10px] font-bold cursor-pointer"
                  >Emerald</button>
                  <button
                    type="button"
                    onClick={() => { setRoleBadgeBg('bg-amber-100'); setRoleBadgeText('text-amber-800'); setRoleBadgeBorder('border-amber-200'); }}
                    className="flex-1 py-2 rounded-lg bg-amber-100 text-amber-800 border border-amber-200 text-[10px] font-bold cursor-pointer"
                  >Amber</button>
                  <button
                    type="button"
                    onClick={() => { setRoleBadgeBg('bg-purple-100'); setRoleBadgeText('text-purple-800'); setRoleBadgeBorder('border-purple-200'); }}
                    className="flex-1 py-2 rounded-lg bg-purple-100 text-purple-800 border border-purple-200 text-[10px] font-bold cursor-pointer"
                  >Purple</button>
                </div>
              </div>

              <div className="sm:col-span-2">
                <label className="block text-xs font-bold text-slate-700 mb-1">Description</label>
                <input 
                  type="text"
                  placeholder="e.g. Authorized to process POS transactions and view delivery logs..."
                  className="w-full px-3 py-2 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:outline-none text-xs font-medium bg-white"
                  value={roleDesc}
                  onChange={(e) => setRoleDesc(e.target.value)}
                />
              </div>
            </div>

            {/* ROLE CRUD MATRIX */}
            <div className="space-y-3 bg-indigo-50/40 p-4 rounded-xl border border-indigo-100">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-indigo-100 pb-2">
                <div>
                  <span className="text-[10px] font-black uppercase text-indigo-900 tracking-wider block">
                    Role CRUD Matrix Configuration
                  </span>
                  <p className="text-[11px] text-slate-500 font-medium">
                    Check the boxes below to define what members with this role can View, Create, Edit, and Delete.
                  </p>
                </div>

                <div className="flex flex-wrap gap-1.5 text-[10px] font-bold">
                  <button type="button" onClick={() => batchUpdateRolePermissions('all')} className="px-2 py-0.5 bg-indigo-600 text-white rounded hover:bg-indigo-700 transition-colors cursor-pointer">Enable All</button>
                  <button type="button" onClick={() => batchUpdateRolePermissions('view')} className="px-2 py-0.5 bg-blue-100 text-blue-800 rounded hover:bg-blue-200 transition-colors cursor-pointer">Toggle View</button>
                  <button type="button" onClick={() => batchUpdateRolePermissions('create')} className="px-2 py-0.5 bg-emerald-100 text-emerald-800 rounded hover:bg-emerald-200 transition-colors cursor-pointer">Toggle Create</button>
                  <button type="button" onClick={() => batchUpdateRolePermissions('edit')} className="px-2 py-0.5 bg-indigo-100 text-indigo-800 rounded hover:bg-indigo-200 transition-colors cursor-pointer">Toggle Edit</button>
                  <button type="button" onClick={() => batchUpdateRolePermissions('delete')} className="px-2 py-0.5 bg-rose-100 text-rose-800 rounded hover:bg-rose-200 transition-colors cursor-pointer">Toggle Delete</button>
                  <button type="button" onClick={() => batchUpdateRolePermissions('clear')} className="px-2 py-0.5 bg-slate-200 text-slate-700 rounded hover:bg-slate-300 transition-colors cursor-pointer">Clear All</button>
                </div>
              </div>

              <div className="overflow-x-auto">
                <table className="w-full text-left border-collapse">
                  <thead>
                    <tr className="border-b border-indigo-100 text-[10px] uppercase font-bold text-slate-500">
                      <th className="py-2 px-2">Module / Feature</th>
                      <th className="py-2 px-2 text-center text-blue-700">View</th>
                      <th className="py-2 px-2 text-center text-emerald-700">Create</th>
                      <th className="py-2 px-2 text-center text-indigo-700">Edit</th>
                      <th className="py-2 px-2 text-center text-rose-700">Delete</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-indigo-100/60">
                    {ALL_SYSTEM_MODULES.map((m) => {
                      const perm = roleModulePermissions[m.id] || { view: false, create: false, edit: false, delete: false };
                      const IconComp = MODULE_ICONS[m.id] || Package;

                      return (
                        <tr key={m.id} className="hover:bg-indigo-50/50 transition-colors">
                          <td className="py-2 px-2">
                            <div className="flex items-center gap-2">
                              <IconComp size={15} className="text-indigo-600 shrink-0" />
                              <div>
                                <span className="text-xs font-bold text-slate-800 block leading-tight">{m.label}</span>
                                <span className="text-[10px] text-slate-400 font-medium">{m.desc}</span>
                              </div>
                            </div>
                          </td>

                          {/* View */}
                          <td className="py-2 px-2 text-center">
                            <button
                              type="button"
                              onClick={() => toggleRoleModuleAction(m.id, 'view')}
                              className={`p-1.5 rounded-lg border transition-all cursor-pointer ${
                                perm.view ? 'bg-blue-600 border-blue-600 text-white' : 'bg-white border-slate-300 text-slate-300 hover:border-slate-400'
                              }`}
                            >
                              <Check size={14} />
                            </button>
                          </td>

                          {/* Create */}
                          <td className="py-2 px-2 text-center">
                            <button
                              type="button"
                              onClick={() => toggleRoleModuleAction(m.id, 'create')}
                              className={`p-1.5 rounded-lg border transition-all cursor-pointer ${
                                perm.create ? 'bg-emerald-600 border-emerald-600 text-white' : 'bg-white border-slate-300 text-slate-300 hover:border-slate-400'
                              }`}
                            >
                              <Check size={14} />
                            </button>
                          </td>

                          {/* Edit */}
                          <td className="py-2 px-2 text-center">
                            <button
                              type="button"
                              onClick={() => toggleRoleModuleAction(m.id, 'edit')}
                              className={`p-1.5 rounded-lg border transition-all cursor-pointer ${
                                perm.edit ? 'bg-indigo-600 border-indigo-600 text-white' : 'bg-white border-slate-300 text-slate-300 hover:border-slate-400'
                              }`}
                            >
                              <Check size={14} />
                            </button>
                          </td>

                          {/* Delete */}
                          <td className="py-2 px-2 text-center">
                            <button
                              type="button"
                              onClick={() => toggleRoleModuleAction(m.id, 'delete')}
                              className={`p-1.5 rounded-lg border transition-all cursor-pointer ${
                                perm.delete ? 'bg-rose-600 border-rose-600 text-white' : 'bg-white border-slate-300 text-slate-300 hover:border-slate-400'
                              }`}
                            >
                              <Check size={14} />
                            </button>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            </div>

            {/* Footer buttons */}
            <div className="flex gap-3 pt-3 border-t border-slate-100">
              <button 
                type="button"
                onClick={() => setIsRoleModalOpen(false)}
                className="flex-1 py-2.5 border border-slate-200 text-slate-600 rounded-xl hover:bg-slate-50 transition-colors text-xs font-bold cursor-pointer"
              >
                Cancel
              </button>
              <button 
                type="submit"
                className="flex-1 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-lg shadow-indigo-100 transition-all cursor-pointer"
              >
                Save Role Configuration
              </button>
            </div>
          </form>
        </div>
      )}
    </div>
  );
}
