import React, { useEffect, useState } from 'react';
import { apiFetch, qs } from '@/api/client';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { ChevronLeft, ChevronRight } from 'lucide-react';

interface Movement {
  id: string; movement_type: string; qty: number; prev_stock: number; new_stock: number;
  remarks: string | null; created_at: string;
  products: { name: string; sku: string } | null;
}

interface Product { id: string; name: string; }

const ITEMS_PER_PAGE = 15;

const MovementsPage: React.FC = () => {
  const [movements, setMovements] = useState<Movement[]>([]);
  const [products, setProducts] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(0);
  const [total, setTotal] = useState(0);
  const [filterType, setFilterType] = useState('all');
  const [filterProduct, setFilterProduct] = useState('all');
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');

  const fetchData = async () => {
    setLoading(true);
    const res = await apiFetch<{ items: Movement[]; total: number }>(
      `/movements.php${qs({
        type: filterType,
        product_id: filterProduct,
        date_from: dateFrom,
        date_to: dateTo,
        page: page + 1,
        per_page: ITEMS_PER_PAGE,
      })}`
    );

    if (res.ok) {
      setMovements(res.items || []);
      setTotal(res.total || 0);
    } else {
      setMovements([]);
      setTotal(0);
    }
    setLoading(false);
  };

  useEffect(() => {
    (async () => {
      const res = await apiFetch<{ productsActive: Product[] }>('/lookups.php');
      if (res.ok) setProducts(res.productsActive || []);
      else setProducts([]);
    })();
  }, []);

  useEffect(() => { fetchData(); }, [filterType, filterProduct, dateFrom, dateTo, page]);

  const totalPages = Math.ceil(total / ITEMS_PER_PAGE);

  return (
    <div>
      <div className="page-header"><h1 className="page-title">Stock Movements</h1></div>

      <div className="flex flex-wrap gap-3 mb-4">
        <Select value={filterType} onValueChange={(v) => { setFilterType(v); setPage(0); }}>
          <SelectTrigger className="w-[140px]"><SelectValue placeholder="All Types" /></SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All Types</SelectItem>
            <SelectItem value="IN">Stock In</SelectItem>
            <SelectItem value="OUT">Stock Out</SelectItem>
            <SelectItem value="ADJUST">Adjustment</SelectItem>
          </SelectContent>
        </Select>
        <Select value={filterProduct} onValueChange={(v) => { setFilterProduct(v); setPage(0); }}>
          <SelectTrigger className="w-[200px]"><SelectValue placeholder="All Products" /></SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All Products</SelectItem>
            {products.map(p => <SelectItem key={p.id} value={p.id}>{p.name}</SelectItem>)}
          </SelectContent>
        </Select>
        <div className="flex items-center gap-2">
          <Label className="text-sm text-muted-foreground">From</Label>
          <Input type="date" value={dateFrom} onChange={(e) => { setDateFrom(e.target.value); setPage(0); }} className="w-[160px]" />
        </div>
        <div className="flex items-center gap-2">
          <Label className="text-sm text-muted-foreground">To</Label>
          <Input type="date" value={dateTo} onChange={(e) => { setDateTo(e.target.value); setPage(0); }} className="w-[160px]" />
        </div>
      </div>

      <div className="table-container">
        <table className="w-full text-sm">
          <thead><tr className="border-b bg-muted/50">
            <th className="px-4 py-3 text-left font-medium text-muted-foreground">Type</th>
            <th className="px-4 py-3 text-left font-medium text-muted-foreground">Product</th>
            <th className="px-4 py-3 text-left font-medium text-muted-foreground">Qty</th>
            <th className="px-4 py-3 text-left font-medium text-muted-foreground">Prev Stock</th>
            <th className="px-4 py-3 text-left font-medium text-muted-foreground">New Stock</th>
            <th className="px-4 py-3 text-left font-medium text-muted-foreground">Remarks</th>
            <th className="px-4 py-3 text-left font-medium text-muted-foreground">Date</th>
          </tr></thead>
          <tbody>
            {loading ? <tr><td colSpan={7} className="px-4 py-8 text-center text-muted-foreground">Loading...</td></tr>
              : movements.length === 0 ? <tr><td colSpan={7} className="px-4 py-8 text-center text-muted-foreground">No movements found</td></tr>
              : movements.map(m => (
                <tr key={m.id} className="border-b last:border-0 hover:bg-muted/30">
                  <td className="px-4 py-3">
                    <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                      m.movement_type === 'IN' ? 'bg-success/10 text-success' : m.movement_type === 'OUT' ? 'bg-destructive/10 text-destructive' : 'bg-warning/10 text-warning'
                    }`}>{m.movement_type}</span>
                  </td>
                  <td className="px-4 py-3 font-medium">{m.products?.name || '-'}</td>
                  <td className="px-4 py-3">{m.qty}</td>
                  <td className="px-4 py-3 text-muted-foreground">{m.prev_stock}</td>
                  <td className="px-4 py-3">{m.new_stock}</td>
                  <td className="px-4 py-3 text-muted-foreground truncate max-w-[200px]">{m.remarks || '-'}</td>
                  <td className="px-4 py-3 text-muted-foreground">{new Date(m.created_at).toLocaleString()}</td>
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

export default MovementsPage;
