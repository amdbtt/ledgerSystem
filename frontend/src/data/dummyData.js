const clients = [
  {
    _id: 'client-1',
    name: 'Acme Trading Co.',
    country: 'United States',
    address: '120 Market Street, San Francisco, CA',
    phone: '+1 415 555 0142',
    email: 'billing@acmetrading.example',
  },
  {
    _id: 'client-2',
    name: 'Northwind Retail',
    country: 'Canada',
    address: '88 King Street West, Toronto, ON',
    phone: '+1 416 555 0198',
    email: 'accounts@northwind.example',
  },
  {
    _id: 'client-3',
    name: 'Bright Labs GmbH',
    country: 'Germany',
    address: 'Friedrichstrasse 50, Berlin',
    phone: '+49 30 555 2211',
    email: 'finance@brightlabs.example',
  },
  {
    _id: 'client-4',
    name: 'Sahara Supplies',
    country: 'United Arab Emirates',
    address: 'Business Bay, Dubai',
    phone: '+971 4 555 9080',
    email: 'orders@saharasupplies.example',
  },
];

const paymentModes = [
  {
    _id: 'pm-1',
    name: 'Bank Transfer',
    description: 'Direct bank wire',
    isDefault: true,
    enabled: true,
  },
  {
    _id: 'pm-2',
    name: 'Credit Card',
    description: 'Visa / Mastercard',
    isDefault: false,
    enabled: true,
  },
  {
    _id: 'pm-3',
    name: 'Cash',
    description: 'Cash payment',
    isDefault: false,
    enabled: true,
  },
];

const taxes = [
  {
    _id: 'tax-1',
    taxName: 'VAT 10%',
    taxValue: 10,
    isDefault: true,
    enabled: true,
  },
  {
    _id: 'tax-2',
    taxName: 'Sales Tax 5%',
    taxValue: 5,
    isDefault: false,
    enabled: true,
  },
  {
    _id: 'tax-3',
    taxName: 'Zero Tax',
    taxValue: 0,
    isDefault: false,
    enabled: true,
  },
];

const invoices = [
  {
    _id: 'inv-1',
    number: 1001,
    year: 2026,
    date: '2026-03-01',
    expiredDate: '2026-03-31',
    client: clients[0],
    currency: 'PKR',
    status: 'pending',
    paymentStatus: 'unpaid',
    subTotal: 1200,
    taxRate: 10,
    taxTotal: 120,
    total: 1320,
    credit: 0,
    discount: 0,
    items: [
      {
        _id: 'inv-1-item-1',
        itemName: 'Website Redesign',
        description: 'Landing page and branding refresh',
        price: 800,
        quantity: 1,
        total: 800,
      },
      {
        _id: 'inv-1-item-2',
        itemName: 'Hosting Setup',
        description: 'Annual hosting configuration',
        price: 200,
        quantity: 2,
        total: 400,
      },
    ],
  },
  {
    _id: 'inv-2',
    number: 1002,
    year: 2026,
    date: '2026-03-10',
    expiredDate: '2026-04-10',
    client: clients[1],
    currency: 'PKR',
    status: 'sent',
    paymentStatus: 'partially',
    subTotal: 2500,
    taxRate: 10,
    taxTotal: 250,
    total: 2750,
    credit: 1000,
    discount: 0,
    items: [
      {
        _id: 'inv-2-item-1',
        itemName: 'Inventory System',
        description: 'Module license',
        price: 2500,
        quantity: 1,
        total: 2500,
      },
    ],
  },
  {
    _id: 'inv-3',
    number: 1003,
    year: 2026,
    date: '2026-02-15',
    expiredDate: '2026-03-15',
    client: clients[2],
    currency: 'PKR',
    status: 'paid',
    paymentStatus: 'paid',
    subTotal: 900,
    taxRate: 5,
    taxTotal: 45,
    total: 945,
    credit: 945,
    discount: 0,
    items: [
      {
        _id: 'inv-3-item-1',
        itemName: 'Support Retainer',
        description: 'February support hours',
        price: 90,
        quantity: 10,
        total: 900,
      },
    ],
  },
  {
    _id: 'inv-4',
    number: 1004,
    year: 2026,
    date: '2026-01-20',
    expiredDate: '2026-02-20',
    client: clients[3],
    currency: 'PKR',
    status: 'overdue',
    paymentStatus: 'unpaid',
    subTotal: 1800,
    taxRate: 10,
    taxTotal: 180,
    total: 1980,
    credit: 0,
    discount: 0,
    items: [
      {
        _id: 'inv-4-item-1',
        itemName: 'Warehouse Audit',
        description: 'On-site inventory audit',
        price: 1800,
        quantity: 1,
        total: 1800,
      },
    ],
  },
];

const quotes = [
  {
    _id: 'quote-1',
    number: 501,
    year: 2026,
    date: '2026-03-05',
    expiredDate: '2026-04-05',
    client: clients[0],
    currency: 'PKR',
    status: 'draft',
    subTotal: 1500,
    taxRate: 10,
    taxTotal: 150,
    total: 1650,
    credit: 0,
    discount: 0,
    items: [
      {
        _id: 'quote-1-item-1',
        itemName: 'CRM Onboarding',
        description: 'Setup and training package',
        price: 1500,
        quantity: 1,
        total: 1500,
      },
    ],
  },
  {
    _id: 'quote-2',
    number: 502,
    year: 2026,
    date: '2026-03-12',
    expiredDate: '2026-04-12',
    client: clients[1],
    currency: 'PKR',
    status: 'sent',
    subTotal: 3200,
    taxRate: 10,
    taxTotal: 320,
    total: 3520,
    credit: 0,
    discount: 0,
    items: [
      {
        _id: 'quote-2-item-1',
        itemName: 'Custom Reports',
        description: 'Dashboard and export suite',
        price: 1600,
        quantity: 2,
        total: 3200,
      },
    ],
  },
  {
    _id: 'quote-3',
    number: 503,
    year: 2026,
    date: '2026-02-28',
    expiredDate: '2026-03-28',
    client: clients[2],
    currency: 'PKR',
    status: 'accepted',
    subTotal: 750,
    taxRate: 5,
    taxTotal: 37.5,
    total: 787.5,
    credit: 0,
    discount: 0,
    items: [
      {
        _id: 'quote-3-item-1',
        itemName: 'Email Templates',
        description: 'Invoice and quote templates',
        price: 250,
        quantity: 3,
        total: 750,
      },
    ],
  },
];

const payments = [
  {
    _id: 'pay-1',
    number: 2001,
    year: 2026,
    date: '2026-03-11',
    amount: 1000,
    currency: 'PKR',
    status: 'success',
    client: clients[1],
    invoice: invoices[1],
    paymentMode: paymentModes[0],
    subTotal: invoices[1].subTotal,
    total: invoices[1].total,
  },
  {
    _id: 'pay-2',
    number: 2002,
    year: 2026,
    date: '2026-02-16',
    amount: 945,
    currency: 'PKR',
    status: 'success',
    client: clients[2],
    invoice: invoices[2],
    paymentMode: paymentModes[1],
    subTotal: invoices[2].subTotal,
    total: invoices[2].total,
  },
  {
    _id: 'pay-3',
    number: 2003,
    year: 2026,
    date: '2026-03-18',
    amount: 500,
    currency: 'PKR',
    status: 'success',
    client: clients[0],
    invoice: invoices[0],
    paymentMode: paymentModes[2],
    subTotal: invoices[0].subTotal,
    total: invoices[0].total,
  },
];

const settings = [
  {
    settingCategory: 'money_format_settings',
    settingKey: 'default_currency_code',
    settingValue: 'PKR',
  },
  {
    settingCategory: 'money_format_settings',
    settingKey: 'currency_code',
    settingValue: 'PKR',
  },
  {
    settingCategory: 'money_format_settings',
    settingKey: 'currency_name',
    settingValue: 'Pakistani Rupee',
  },
  {
    settingCategory: 'money_format_settings',
    settingKey: 'currency_symbol',
    settingValue: 'Rs',
  },
  {
    settingCategory: 'money_format_settings',
    settingKey: 'currency_position',
    settingValue: 'before',
  },
  {
    settingCategory: 'money_format_settings',
    settingKey: 'decimal_sep',
    settingValue: '.',
  },
  {
    settingCategory: 'money_format_settings',
    settingKey: 'thousand_sep',
    settingValue: ',',
  },
  {
    settingCategory: 'money_format_settings',
    settingKey: 'cent_precision',
    settingValue: 2,
  },
  {
    settingCategory: 'money_format_settings',
    settingKey: 'zero_format',
    settingValue: false,
  },
  {
    settingCategory: 'company_settings',
    settingKey: 'company_name',
    settingValue: 'Ledger Preview Co.',
  },
  {
    settingCategory: 'company_settings',
    settingKey: 'company_address',
    settingValue: '42 Demo Avenue, Suite 100',
  },
  {
    settingCategory: 'company_settings',
    settingKey: 'company_email',
    settingValue: 'hello@ledgerpreview.example',
  },
  {
    settingCategory: 'company_settings',
    settingKey: 'company_phone',
    settingValue: '+1 555 0100',
  },
  {
    settingCategory: 'app_settings',
    settingKey: 'idurar_app_date_format',
    settingValue: 'DD/MM/YYYY',
  },
  {
    settingCategory: 'finance_settings',
    settingKey: 'last_invoice_number',
    settingValue: 1004,
  },
  {
    settingCategory: 'finance_settings',
    settingKey: 'last_quote_number',
    settingValue: 503,
  },
  {
    settingCategory: 'finance_settings',
    settingKey: 'last_payment_number',
    settingValue: 2003,
  },
];

const summaries = {
  invoice: {
    total: 6995,
    total_undue: 3300,
    performance: [
      { status: 'draft', percentage: 10 },
      { status: 'pending', percentage: 25 },
      { status: 'overdue', percentage: 20 },
      { status: 'paid', percentage: 30 },
      { status: 'unpaid', percentage: 15 },
    ],
  },
  quote: {
    total: 5957.5,
    total_undue: 0,
    performance: [
      { status: 'draft', percentage: 20 },
      { status: 'pending', percentage: 15 },
      { status: 'sent', percentage: 35 },
      { status: 'accepted', percentage: 25 },
      { status: 'declined', percentage: 5 },
    ],
  },
  payment: {
    total: 2445,
    total_undue: 0,
    performance: [],
  },
  client: {
    total: 4,
    active: 3,
    new: 1,
  },
};

const store = {
  client: clients,
  invoice: invoices,
  quote: quotes,
  payment: payments,
  paymentMode: paymentModes,
  taxes,
  setting: settings,
};

let idCounter = 1000;

function nextId(prefix) {
  idCounter += 1;
  return `${prefix}-${idCounter}`;
}

function paginate(items, options = {}) {
  const page = parseInt(options.page || 1, 10);
  const itemsPerPage = parseInt(options.items || options.count || 10, 10);
  const start = (page - 1) * itemsPerPage;
  const slice = items.slice(start, start + itemsPerPage);
  return {
    success: true,
    result: slice,
    pagination: {
      page,
      count: items.length,
    },
  };
}

function getCollection(entity) {
  return store[entity] || null;
}

export function listEntity(entity, options = {}) {
  const collection = getCollection(entity);
  if (!collection) {
    return {
      success: true,
      result: [],
      pagination: { page: 1, count: 0 },
    };
  }
  return paginate(collection, options);
}

export function listAllEntity(entity) {
  if (entity === 'setting') {
    return { success: true, result: store.setting };
  }
  const collection = getCollection(entity);
  return { success: true, result: collection ? [...collection] : [] };
}

export function readEntity(entity, id) {
  const collection = getCollection(entity);
  if (!collection) {
    return { success: true, result: {} };
  }
  const found = collection.find((item) => String(item._id) === String(id));
  return { success: true, result: found ? { ...found } : {} };
}

export function searchEntity(entity, options = {}) {
  const collection = getCollection(entity) || [];
  const query = String(options.q || options.filter || '').toLowerCase();
  if (!query) {
    return paginate(collection, options);
  }
  const filtered = collection.filter((item) =>
    JSON.stringify(item).toLowerCase().includes(query)
  );
  return paginate(filtered, options);
}

export function filterEntity(entity, options = {}) {
  return searchEntity(entity, options);
}

export function createEntity(entity, jsonData = {}) {
  const collection = getCollection(entity);
  if (!collection) {
    return { success: true, result: { ...jsonData, _id: nextId(entity) } };
  }
  const created = {
    ...jsonData,
    _id: nextId(entity),
  };
  collection.unshift(created);
  return { success: true, result: created };
}

export function updateEntity(entity, id, jsonData = {}) {
  const collection = getCollection(entity);
  if (!collection) {
    return { success: true, result: { ...jsonData, _id: id } };
  }
  const index = collection.findIndex((item) => String(item._id) === String(id));
  if (index === -1) {
    return { success: true, result: { ...jsonData, _id: id } };
  }
  collection[index] = { ...collection[index], ...jsonData, _id: id };
  return { success: true, result: collection[index] };
}

export function deleteEntity(entity, id) {
  const collection = getCollection(entity);
  if (!collection) {
    return { success: true, result: {} };
  }
  const index = collection.findIndex((item) => String(item._id) === String(id));
  if (index !== -1) {
    const [removed] = collection.splice(index, 1);
    return { success: true, result: removed };
  }
  return { success: true, result: {} };
}

export function summaryEntity(entity) {
  return {
    success: true,
    result: summaries[entity] || {
      total: 0,
      total_undue: 0,
      performance: [],
      active: 0,
      new: 0,
    },
  };
}
