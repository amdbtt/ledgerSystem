import {
  listEntity,
  listAllEntity,
  readEntity,
  searchEntity,
  filterEntity,
  createEntity,
  updateEntity,
  deleteEntity,
  summaryEntity,
} from '@/data/dummyData';

const emptyResult = { success: true, result: {} };

const request = {
  create: async ({ entity, jsonData }) => createEntity(entity, jsonData),
  createAndUpload: async ({ entity, jsonData }) => createEntity(entity, jsonData),
  read: async ({ entity, id }) => readEntity(entity, id),
  update: async ({ entity, id, jsonData }) => updateEntity(entity, id, jsonData),
  updateAndUpload: async ({ entity, id, jsonData }) => updateEntity(entity, id, jsonData),
  delete: async ({ entity, id }) => deleteEntity(entity, id),
  filter: async ({ entity, options = {} }) => filterEntity(entity, options),
  search: async ({ entity, options = {} }) => searchEntity(entity, options),
  list: async ({ entity, options = {} }) => listEntity(entity, options),
  listAll: async ({ entity }) => listAllEntity(entity),
  post: async () => emptyResult,
  get: async () => emptyResult,
  patch: async ({ entity, jsonData }) => {
    if (typeof entity === 'string' && entity.includes('setting')) {
      return { success: true, result: jsonData || {} };
    }
    return emptyResult;
  },
  upload: async () => emptyResult,
  source: () => ({ token: null, cancel() {} }),
  summary: async ({ entity }) => summaryEntity(entity),
  mail: async () => emptyResult,
  convert: async ({ entity, id }) => {
    const quote = readEntity(entity, id);
    if (!quote.result?._id) {
      return emptyResult;
    }
    const invoice = createEntity('invoice', {
      ...quote.result,
      status: 'pending',
      paymentStatus: 'unpaid',
      credit: 0,
      number: Math.floor(Math.random() * 9000) + 1000,
    });
    return { success: true, result: invoice.result };
  },
};

export default request;
