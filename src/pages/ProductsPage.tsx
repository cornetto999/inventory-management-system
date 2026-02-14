import React, { useEffect, useState } from 'react';
import { apiFetch, qs } from '@/api/client';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Plus, Search, Edit, Trash2, ChevronLeft, ChevronRight } from 'lucide-react';
import { toast } from 'sonner';

interface Product {
  id: string;
  sku: string;
  name: string;
  category_id: string;
  supplier_id: string | null;
  unit: string;
  cost_price: number;
  selling_price: number;
  stock: number;
  reorder_level: number;
  status: string;
  created_at: string;
  categories?: { name: string } | null;
  suppliers?: { name: string } | null;
}

interface Category { id: string; name: string; }
interface Supplier { id: string; name: string; }

const ITEMS_PER_PAGE = 10;

const ProductsPage: React.FC = () => {
  const [products, setProducts] = useState<Product[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [suppliers, setSuppliers] = useState<Supplier[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [filterCategory, setFilterCategory] = useState('all');
  const [filterStatus, setFilterStatus] = useState('all');
  const [page, setPage] = useState(0);
  const [total, setTotal] = useState(0);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editing, setEditing] = useState<Product | null>(null);
  const [form, setForm] = useState({
    sku: '', name: '', category_id: '', supplier_id: '', unit: 'pcs',
    cost_price: '0', selling_price: '0', stock: '0', reorder_level: '0', status: 'active',
  });
  const [formError, setFormError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const fetchProducts = async () => {
    setLoading(true);
    const res = await apiFetch<{ items: Product[]; total: number }>(
      `/products.php${qs({
        search,
        category_id: filterCategory,
        status: filterStatus,
        page: page + 1,
        per_page: ITEMS_PER_PAGE,
      })}`
    );

    if (res.ok) {
      setProducts(res.items || []);
      setTotal(res.total || 0);
    } else {
      setProducts([]);
      setTotal(0);
    }
    setLoading(false);
  };

  const fetchLookups = async () => {
    const res = await apiFetch<{ categories: Category[]; suppliers: Supplier[] }>('/lookups.php');
    if (res.ok) {
      setCategories(res.categories || []);
      setSuppliers(res.suppliers || []);
    } else {
      setCategories([]);
      setSuppliers([]);
    }
  };

  useEffect(() => { fetchLookups(); }, []);
  useEffect(() => { fetchProducts(); }, [search, filterCategory, filterStatus, page]);

  const openAdd = () => {
    setEditing(null);
    setForm({ sku: '', name: '', category_id: categories[0]?.id || '', supplier_id: '', unit: 'pcs', cost_price: '0', selling_price: '0', stock: '0', reorder_level: '0', status: 'active' });
    setFormError('');
    setDialogOpen(true);
  };

  const openEdit = (p: Product) => {
    setEditing(p);
    setForm({
      sku: p.sku, name: p.name, category_id: p.category_id, supplier_id: p.supplier_id || '',
      unit: p.unit, cost_price: String(p.cost_price), selling_price: String(p.selling_price),
      stock: String(p.stock), reorder_level: String(p.reorder_level), status: p.status,
    });
    setFormError('');
    setDialogOpen(true);
  };

  const handleSubmit = async () => {
    setFormError('');
    if (!form.sku.trim() || !form.name.trim() || !form.category_id) {
      setFormError('SKU, Name, and Category are required.');
      return;
    }
    if (Number(form.cost_price) < 0 || Number(form.selling_price) < 0) {
      setFormError('Prices must be >= 0.');
      return;
    }
    setSubmitting(true);
    const payload = {
      sku: form.sku.trim(),
      name: form.name.trim(),
      category_id: form.category_id,
      supplier_id: form.supplier_id || null,
      unit: form.unit,
      cost_price: Number(form.cost_price),
      selling_price: Number(form.selling_price),
      stock: parseInt(form.stock) || 0,
      reorder_level: parseInt(form.reorder_level) || 0,
      status: form.status,
    };

    const res = editing
      ? await apiFetch<{}>('/products.php', { method: 'PUT', body: JSON.stringify({ id: editing.id, ...payload }) })
      : await apiFetch<{}>('/products.php', { method: 'POST', body: JSON.stringify(payload) });

    if (!res.ok) {
      setFormError(res.error || 'Failed to save product');
    } else {
      toast.success(editing ? 'Product updated' : 'Product created');
      setDialogOpen(false);
      fetchProducts();
    }
    setSubmitting(false);
  };

  const handleDelete = async (id: string) => {
    if (!confirm('Delete this product?')) return;
    const res = await apiFetch<{}>('/products.php', { method: 'DELETE', body: JSON.stringify({ id }) });
    if (!res.ok) toast.error(res.error || 'Failed to delete');
    else { toast.success('Product deleted'); fetchProducts(); }
  };

  const totalPages = Math.ceil(total / ITEMS_PER_PAGE);

  return (
    <div>
      <div className="page-header">
        <h1 className="page-title">Products</h1>
        <Button onClick={openAdd}><Plus className="h-4 w-4 mr-2" />Add Product</Button>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap gap-3 mb-4">
        <div className="relative flex-1 min-w-[200px]">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
          <Input placeholder="Search by name or SKU..." value={search} onChange={(e) => { setSearch(e.target.value); setPage(0); }} className="pl-9" />
        </div>
        <Select value={filterCategory} onValueChange={(v) => { setFilterCategory(v); setPage(0); }}>
          <SelectTrigger className="w-[180px]"><SelectValue placeholder="All Categories" /></SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All Categories</SelectItem>
            {categories.map(c => <SelectItem key={c.id} value={c.id}>{c.name}</SelectItem>)}
          </SelectContent>
        </Select>
        <Select value={filterStatus} onValueChange={(v) => { setFilterStatus(v); setPage(0); }}>
          <SelectTrigger className="w-[140px]"><SelectValue placeholder="All Status" /></SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All Status</SelectItem>
            <SelectItem value="active">Active</SelectItem>
            <SelectItem value="inactive">Inactive</SelectItem>
          </SelectContent>
        </Select>
      </div>

      {/* Table */}
      <div className="table-container">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b bg-muted/50">
                <th className="px-4 py-3 text-left font-medium text-muted-foreground">SKU</th>
                <th className="px-4 py-3 text-left font-medium text-muted-foreground">Name</th>
                <th className="px-4 py-3 text-left font-medium text-muted-foreground">Category</th>
                <th className="px-4 py-3 text-left font-medium text-muted-foreground">Stock</th>
                <th className="px-4 py-3 text-left font-medium text-muted-foreground">Cost</th>
                <th className="px-4 py-3 text-left font-medium text-muted-foreground">Price</th>
                <th className="px-4 py-3 text-left font-medium text-muted-foreground">Status</th>
                <th className="px-4 py-3 text-left font-medium text-muted-foreground">Actions</th>
              </tr>
            </thead>
            <tbody>
              {loading ? (
                <tr><td colSpan={8} className="px-4 py-8 text-center text-muted-foreground">Loading...</td></tr>
              ) : products.length === 0 ? (
                <tr><td colSpan={8} className="px-4 py-8 text-center text-muted-foreground">No products found</td></tr>
              ) : products.map((p) => (
                <tr key={p.id} className="border-b last:border-0 hover:bg-muted/30">
                  <td className="px-4 py-3 font-mono text-xs">{p.sku}</td>
                  <td className="px-4 py-3 font-medium">{p.name}</td>
                  <td className="px-4 py-3 text-muted-foreground">{p.categories?.name || '-'}</td>
                  <td className="px-4 py-3">
                    <span className={p.stock <= p.reorder_level ? 'text-destructive font-semibold' : ''}>{p.stock}</span>
                  </td>
                  <td className="px-4 py-3">₱{Number(p.cost_price).toFixed(2)}</td>
                  <td className="px-4 py-3">₱{Number(p.selling_price).toFixed(2)}</td>
                  <td className="px-4 py-3">
                    <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                      p.status === 'active' ? 'bg-success/10 text-success' : 'bg-muted text-muted-foreground'
                    }`}>{p.status}</span>
                  </td>
                  <td className="px-4 py-3">
                    <div className="flex gap-1">
                      <Button size="sm" variant="ghost" onClick={() => openEdit(p)}><Edit className="h-3.5 w-3.5" /></Button>
                      <Button size="sm" variant="ghost" onClick={() => handleDelete(p.id)} className="text-destructive hover:text-destructive"><Trash2 className="h-3.5 w-3.5" /></Button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        {totalPages > 1 && (
          <div className="flex items-center justify-between p-4 border-t">
            <p className="text-sm text-muted-foreground">Showing {page * ITEMS_PER_PAGE + 1}-{Math.min((page + 1) * ITEMS_PER_PAGE, total)} of {total}</p>
            <div className="flex gap-1">
              <Button size="sm" variant="outline" disabled={page === 0} onClick={() => setPage(p => p - 1)}><ChevronLeft className="h-4 w-4" /></Button>
              <Button size="sm" variant="outline" disabled={page >= totalPages - 1} onClick={() => setPage(p => p + 1)}><ChevronRight className="h-4 w-4" /></Button>
            </div>
          </div>
        )}
      </div>

      {/* Dialog */}
      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="max-w-lg">
          <DialogHeader>
            <DialogTitle>{editing ? 'Edit Product' : 'Add Product'}</DialogTitle>
          </DialogHeader>
          {formError && <div className="rounded-md bg-destructive/10 border border-destructive/20 p-3 text-sm text-destructive">{formError}</div>}
          <div className="grid grid-cols-2 gap-4">
            <div>
              <Label>SKU *</Label>
              <Input value={form.sku} onChange={(e) => setForm({ ...form, sku: e.target.value })} maxLength={50} className="mt-1" />
            </div>
            <div>
              <Label>Name *</Label>
              <Input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} maxLength={200} className="mt-1" />
            </div>
            <div>
              <Label>Category *</Label>
              <Select value={form.category_id} onValueChange={(v) => setForm({ ...form, category_id: v })}>
                <SelectTrigger className="mt-1"><SelectValue placeholder="Select" /></SelectTrigger>
                <SelectContent>{categories.map(c => <SelectItem key={c.id} value={c.id}>{c.name}</SelectItem>)}</SelectContent>
              </Select>
            </div>
            <div>
              <Label>Supplier</Label>
              <Select value={form.supplier_id || 'none'} onValueChange={(v) => setForm({ ...form, supplier_id: v === 'none' ? '' : v })}>
                <SelectTrigger className="mt-1"><SelectValue placeholder="Optional" /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="none">None</SelectItem>
                  {suppliers.map(s => <SelectItem key={s.id} value={s.id}>{s.name}</SelectItem>)}
                </SelectContent>
              </Select>
            </div>
            <div>
              <Label>Unit</Label>
              <Select value={form.unit} onValueChange={(v) => setForm({ ...form, unit: v })}>
                <SelectTrigger className="mt-1"><SelectValue /></SelectTrigger>
                <SelectContent>
                  {['pcs', 'box', 'kg', 'liter', 'meter', 'set'].map(u => <SelectItem key={u} value={u}>{u}</SelectItem>)}
                </SelectContent>
              </Select>
            </div>
            <div>
              <Label>Status</Label>
              <Select value={form.status} onValueChange={(v) => setForm({ ...form, status: v })}>
                <SelectTrigger className="mt-1"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="active">Active</SelectItem>
                  <SelectItem value="inactive">Inactive</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div>
              <Label>Cost Price *</Label>
              <Input type="number" min="0" step="0.01" value={form.cost_price} onChange={(e) => setForm({ ...form, cost_price: e.target.value })} className="mt-1" />
            </div>
            <div>
              <Label>Selling Price *</Label>
              <Input type="number" min="0" step="0.01" value={form.selling_price} onChange={(e) => setForm({ ...form, selling_price: e.target.value })} className="mt-1" />
            </div>
            <div>
              <Label>Stock</Label>
              <Input type="number" min="0" value={form.stock} onChange={(e) => setForm({ ...form, stock: e.target.value })} className="mt-1" />
            </div>
            <div>
              <Label>Reorder Level</Label>
              <Input type="number" min="0" value={form.reorder_level} onChange={(e) => setForm({ ...form, reorder_level: e.target.value })} className="mt-1" />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDialogOpen(false)}>Cancel</Button>
            <Button onClick={handleSubmit} disabled={submitting}>{submitting ? 'Saving...' : 'Save'}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
};

export default ProductsPage;
