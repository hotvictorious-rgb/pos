import express from 'express';
import path from 'path';
import { fileURLToPath } from 'url';
import { createServer as createViteServer } from 'vite';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();
const PORT = 3000;

app.use(express.json({ limit: '50mb' }));

// Memory Data Store
let currentUser: any = {
  id: 'admin-user-1',
  name: 'Admin User',
  email: 'admin@hysam.com',
  role: 'admin',
  disabled: false,
  permissions: {
    create: true,
    edit: true,
    delete: true,
    stockIn: true,
    stockOut: true
  },
  createdAt: new Date().toISOString()
};

let store = {
  users: [
    {
      id: 'admin-user-1',
      name: 'Admin User',
      email: 'admin@hysam.com',
      role: 'admin',
      disabled: false,
      permissions: {
        create: true,
        edit: true,
        delete: true,
        stockIn: true,
        stockOut: true
      },
      createdAt: new Date().toISOString()
    },
    {
      id: 'staff-user-1',
      name: 'Staff User',
      email: 'staff@hysam.com',
      role: 'staff',
      disabled: false,
      permissions: {
        create: true,
        edit: false,
        delete: false,
        stockIn: true,
        stockOut: false
      },
      createdAt: new Date().toISOString()
    }
  ],
  products: [
    {
      id: 'p1',
      code: 'GEN-001',
      name: 'Industrial Generator',
      size: '500kVA',
      brand: 'Cummins',
      description: 'High capacity power backup generator',
      category: 'Power',
      unitPrice: 250000,
      currentStock: 10,
      minStockLevel: 2,
      archived: false,
      userId: 'admin-user-1',
      updatedAt: new Date().toISOString()
    },
    {
      id: 'p2',
      code: 'SOL-400',
      name: 'Solar Panel',
      size: '400W',
      brand: 'Jinko',
      description: 'Monocrystalline high-efficiency solar panel',
      category: 'Solar',
      unitPrice: 45000,
      currentStock: 50,
      minStockLevel: 10,
      archived: false,
      userId: 'admin-user-1',
      updatedAt: new Date().toISOString()
    }
  ],
  sales: [] as any[],
  payments: [] as any[],
  logs: [] as any[],
  returns: [] as any[],
  activities: [] as any[],
  settings: {
    businessName: 'HYSAM VENTURES',
    businessAddress: '123 Main Street, Lagos, Nigeria',
    businessPhone: '+234 800 000 0000',
    businessEmail: 'info@hysam.com',
    currency: '₦',
    categories: ['Power', 'Solar', 'Battery', 'Inverter', 'Accessories', 'General'],
    reportFooter: 'Thank you for your business!',
    lowStockThreshold: 5,
    transactionEditLimitDays: 7,
    fontFamily: 'Inter'
  }
};

// API Routes
app.post('/api/login', (req, res) => {
  const { email, password } = req.body;
  if (!email) {
    return res.status(400).json({ error: 'Email address is required.' });
  }

  const normalizedEmail = String(email).trim().toLowerCase();
  const user = store.users.find(u => u.email.toLowerCase() === normalizedEmail);

  if (user) {
    if (user.disabled) {
      return res.status(403).json({ error: 'Your account has been disabled by the administrator.' });
    }
    currentUser = user;
    return res.json(user);
  }

  // Create or return logged-in session user if new
  const newUser = {
    id: `user-${Date.now()}`,
    name: normalizedEmail.split('@')[0] || 'User',
    email: normalizedEmail,
    role: 'admin',
    disabled: false,
    permissions: { create: true, edit: true, delete: true, stockIn: true, stockOut: true },
    createdAt: new Date().toISOString()
  };
  store.users.push(newUser);
  currentUser = newUser;
  return res.json(newUser);
});

app.post('/api/logout', (_req, res) => {
  currentUser = null;
  return res.json({ status: 'logged_out' });
});

app.get('/api/me', (_req, res) => {
  if (currentUser) {
    return res.json(currentUser);
  }
  return res.status(401).json({ error: 'Unauthenticated.' });
});

app.get('/api/data', (_req, res) => {
  return res.json(store);
});

app.post('/api/reset', (_req, res) => {
  store.sales = [];
  store.payments = [];
  store.logs = [];
  store.returns = [];
  store.activities = [];
  store.products = [
    {
      id: 'p1',
      code: 'GEN-001',
      name: 'Industrial Generator',
      size: '500kVA',
      brand: 'Cummins',
      description: 'High capacity power backup generator',
      category: 'Power',
      unitPrice: 250000,
      currentStock: 0,
      minStockLevel: 2,
      archived: false,
      userId: 'admin-user-1',
      updatedAt: new Date().toISOString()
    },
    {
      id: 'p2',
      code: 'SOL-400',
      name: 'Solar Panel',
      size: '400W',
      brand: 'Jinko',
      description: 'Monocrystalline high-efficiency solar panel',
      category: 'Solar',
      unitPrice: 45000,
      currentStock: 0,
      minStockLevel: 10,
      archived: false,
      userId: 'admin-user-1',
      updatedAt: new Date().toISOString()
    }
  ];
  return res.json({ status: 'ok', store });
});

app.post('/api/data', (req, res) => {
  const payload = req.body;
  if (payload) {
    if (Array.isArray(payload.users)) {
      const existingIds = new Set(store.users.map(u => u.id));
      for (const u of payload.users) {
        if (!existingIds.has(u.id)) {
          store.users.push(u);
        } else {
          const idx = store.users.findIndex(item => item.id === u.id);
          if (idx !== -1) store.users[idx] = { ...store.users[idx], ...u };
        }
      }
    }
    if (Array.isArray(payload.products)) store.products = payload.products;
    if (Array.isArray(payload.sales)) store.sales = payload.sales;
    if (Array.isArray(payload.payments)) store.payments = payload.payments;
    if (Array.isArray(payload.logs)) store.logs = payload.logs;
    if (Array.isArray(payload.returns)) store.returns = payload.returns;
    if (Array.isArray(payload.activities)) store.activities = payload.activities;
    if (payload.settings) store.settings = payload.settings;
  }
  return res.json({ status: 'ok' });
});

async function startServer() {
  if (process.env.NODE_ENV !== 'production') {
    const vite = await createViteServer({
      server: { middlewareMode: true },
      appType: 'spa'
    });
    app.use(vite.middlewares);
  } else {
    const distPath = path.join(process.cwd(), 'dist');
    app.use(express.static(distPath));
    app.get('*', (_req, res) => {
      res.sendFile(path.join(distPath, 'index.html'));
    });
  }

  app.listen(PORT, '0.0.0.0', () => {
    console.log(`Server running on http://0.0.0.0:${PORT}`);
  });
}

startServer();
