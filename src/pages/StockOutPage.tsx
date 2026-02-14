import React, { useEffect, useState } from 'react';
import { apiFetch, qs } from '@/api/client';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { toast } from 'sonner';
import { ArrowUpFromLine, ChevronLeft, ChevronRight } from 'lucide-react';

interface Product { id: string; name: string; sku: string; stock: number; }
interface StockOutRecord { id: string; qty: number; remarks: string | null; customer: string | null; created_at: string; products: { name: string; sku: string } | null; }

const ITEMS_PER_PAGE = 10;

const StockOutPage: React.FC = () => {
  const [products, setProducts] = useState<Product[]>([]);
  const [records, setRecords] = useState<StockOutRecord[]>([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(0);
  const [total, setTotal] = useState(0);
  const [form, setForm] = useState({ product_id: '', qty: '', remarks: '', customer: '' });
  const [formError, setFormError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const fetchProducts = async () => {
    const res = await apiFetch<{ productsActive: Product[] }>('/lookups.php');
    if (res.ok) setProducts(res.productsActive || []);
    else setProducts([]);
  };

  const fetchRecords = async () => {
    setLoading(true);
    const res = await apiFetch<{ items: StockOutRecord[]; total: number }>(
      `/stock_out.php${qs({ page: page + 1, per_page: ITEMS_PER_PAGE })}`
    );
    if (res.ok) {
      setRecords(res.items || []);
      setTotal(res.total || 0);
    } else {
      setRecords([]);
      setTotal(0);
    }
    setLoading(false);
  };

  useEffect(() => { fetchProducts(); }, []);
  useEffect(() => { fetchRecords(); }, [page]);

  const selectedProduct = products.find(p => p.id === form.product_id);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setFormError('');
    if (!form.product_id || !form.qty) { setFormError('Product and quantity are required.'); return; }
    const qty = parseInt(form.qty);
    if (isNaN(qty) || qty <= 0) { setFormError('Quantity must be a positive integer.'); return; }

    const product = products.find(p => p.id === form.product_id);
    if (!product) { setFormError('Product not found.'); return; }
    if (qty > product.stock) { setFormError(`Insufficient stock. Available: ${product.stock}`); return; }

    setSubmitting(true);
    const res = await apiFetch<{ id: string }>('/stock_out.php', {
      method: 'POST',
      body: JSON.stringify({
        product_id: form.product_id,
        qty,
        remarks: form.remarks.trim() || null,
        customer: form.customer.trim() || null,
      }),
    });

    if (!res.ok) {
      setFormError(res.error || 'Failed to record stock out');
      setSubmitting(false);
      return;
    }

    toast.success(`Stock out: -${qty} units`);
    setForm({ product_id: '', qty: '', remarks: '', customer: '' });
    fetchRecords();
    fetchProducts();
    setSubmitting(false);
  };

  const totalPages = Math.ceil(total / ITEMS_PER_PAGE);

  return (
    <div>
      <div className="page-header"><h1 className="page-title">Stock Out</h1></div>

      <div className="bg-card border rounded-lg p-5 mb-6">
        <h2 className="text-lg font-semibold mb-4 flex items-center gap-2">
          <ArrowUpFromLine className="h-5 w-5 text-destructive" />
          Record Stock Out
        </h2>
        {formError && <div className="rounded-md bg-destructive/10 border border-destructive/20 p-3 text-sm text-destructive mb-4">{formError}</div>}
        <form onSubmit={handleSubmit} className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <Label>Product *</Label>
            <Select value={form.product_id} onValueChange={(v) => setForm({ ...form, product_id: v })}>
              <SelectTrigger className="mt-1"><SelectValue placeholder="Select product" /></SelectTrigger>
              <SelectContent>{products.map(p => <SelectItem key={p.id} value={p.id}>{p.name} (Stock: {p.stock})</SelectItem>)}</SelectContent>
            </Select>
            {selectedProduct && <p className="text-xs text-muted-foreground mt-1">Available: {selectedProduct.stock} units</p>}
          </div>
          <div>
            <Label>Quantity *</Label>
            <Input type="number" min="1" max={selectedProduct?.stock} value={form.qty} onChange={(e) => setForm({ ...form, qty: e.target.value })} className="mt-1" />
          </div>
          <div>
            <Label>Customer / Department</Label>
            <Input value={form.customer} onChange={(e) => setForm({ ...form, customer: e.target.value })} maxLength={200} className="mt-1" placeholder="Optional" />
          </div>
          <div className="md:col-span-2">
            <Label>Remarks</Label>
            <Textarea value={form.remarks} onChange={(e) => setForm({ ...form, remarks: e.target.value })} maxLength={500} className="mt-1" rows={1} />
          </div>
          <div className="flex items-end">
            <Button type="submit" disabled={submitting} className="w-full">{submitting ? 'Processing...' : 'Submit Stock Out'}</Button>
          </div>
        </form>
      </div>

      <div className="table-container">
        <div className="p-4 border-b"><h2 className="text-lg font-semibold">Stock Out History</h2></div>
        <table className="w-full text-sm">
          <thead><tr className="border-b bg-muted/50">
            <th className="px-4 py-3 text-left font-medium text-muted-foreground">Product</th>
            <th className="px-4 py-3 text-left font-medium text-muted-foreground">Qty</th>
            <th className="px-4 py-3 text-left font-medium text-muted-foreground">Customer</th>
            <th className="px-4 py-3 text-left font-medium text-muted-foreground">Remarks</th>
            <th className="px-4 py-3 text-left font-medium text-muted-foreground">Date</th>
          </tr></thead>
          <tbody>
            {loading ? <tr><td colSpan={5} className="px-4 py-8 text-center text-muted-foreground">Loading...</td></tr>
              : records.length === 0 ? <tr><td colSpan={5} className="px-4 py-8 text-center text-muted-foreground">No records</td></tr>
              : records.map(r => (
                <tr key={r.id} className="border-b last:border-0 hover:bg-muted/30">
                  <td className="px-4 py-3 font-medium">{r.products?.name || '-'}</td>
                  <td className="px-4 py-3 text-destructive font-semibold">-{r.qty}</td>
                  <td className="px-4 py-3 text-muted-foreground">{r.customer || '-'}</td>
                  <td className="px-4 py-3 text-muted-foreground truncate max-w-[150px]">{r.remarks || '-'}</td>
                  <td className="px-4 py-3 text-muted-foreground">{new Date(r.created_at).toLocaleDateString()}</td>
                </tr>
              ))}
          </tbody>
        </table>
        {totalPages > 1 && (
          <div className="flex items-center justify-between p-4 border-t">
            <p className="text-sm text-muted-foreground">Page {page + 1} of {totalPages}</p>
            <div className="flex gap-1">
              <Button size="sm" variant="outline" disabled={page === 0} onClick={() => setPage(p => p - 1)}><ChevronLeft className="h-4 w-4" /></Button>
              <Button size="sm" variant="outline" disabled={page >= totalPages - 1} onClick={() => setPage(p => p + 1)}><ChevronRight className="h-4 w-4" /></Button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default StockOutPage;
