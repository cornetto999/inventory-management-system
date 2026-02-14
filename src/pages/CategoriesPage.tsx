import React, { useEffect, useState } from 'react';
import { apiFetch, qs } from '@/api/client';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Plus, Search, Edit, Trash2, ChevronLeft, ChevronRight } from 'lucide-react';
import { toast } from 'sonner';

interface Category { id: string; name: string; created_at: string; }

const ITEMS_PER_PAGE = 10;

const CategoriesPage: React.FC = () => {
  const [items, setItems] = useState<Category[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(0);
  const [total, setTotal] = useState(0);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editing, setEditing] = useState<Category | null>(null);
  const [name, setName] = useState('');
  const [formError, setFormError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const fetchData = async () => {
    setLoading(true);
    const res = await apiFetch<{ items: Category[]; total: number }>(
      `/categories.php${qs({ search, page: page + 1, per_page: ITEMS_PER_PAGE })}`
    );
    if (res.ok) {
      setItems(res.items || []);
      setTotal(res.total || 0);
    } else {
      setItems([]);
      setTotal(0);
    }
    setLoading(false);
  };

  useEffect(() => { fetchData(); }, [search, page]);

  const handleSubmit = async () => {
    setFormError('');
    const trimmed = name.trim();
    if (!trimmed) { setFormError('Name is required.'); return; }
    if (trimmed.length > 100) { setFormError('Name must be under 100 characters.'); return; }
    setSubmitting(true);

    const res = editing
      ? await apiFetch<{}>('/categories.php', { method: 'PUT', body: JSON.stringify({ id: editing.id, name: trimmed }) })
      : await apiFetch<{}>('/categories.php', { method: 'POST', body: JSON.stringify({ name: trimmed }) });

    if (!res.ok) {
      setFormError(res.error || 'Failed to save');
    } else {
      toast.success(editing ? 'Updated' : 'Created');
      setDialogOpen(false);
      fetchData();
    }
    setSubmitting(false);
  };

  const handleDelete = async (id: string) => {
    if (!confirm('Delete this category?')) return;
    const res = await apiFetch<{}>('/categories.php', { method: 'DELETE', body: JSON.stringify({ id }) });
    if (!res.ok) toast.error(res.error || 'Failed to delete');
    else { toast.success('Deleted'); fetchData(); }
  };

  const totalPages = Math.ceil(total / ITEMS_PER_PAGE);

  return (
    <div>
      <div className="page-header">
        <h1 className="page-title">Categories</h1>
        <Button onClick={() => { setEditing(null); setName(''); setFormError(''); setDialogOpen(true); }}><Plus className="h-4 w-4 mr-2" />Add Category</Button>
      </div>
      <div className="relative mb-4 max-w-sm">
        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
        <Input placeholder="Search categories..." value={search} onChange={(e) => { setSearch(e.target.value); setPage(0); }} className="pl-9" />
      </div>
      <div className="table-container">
        <table className="w-full text-sm">
          <thead><tr className="border-b bg-muted/50">
            <th className="px-4 py-3 text-left font-medium text-muted-foreground">Name</th>
            <th className="px-4 py-3 text-left font-medium text-muted-foreground">Created</th>
            <th className="px-4 py-3 text-left font-medium text-muted-foreground">Actions</th>
          </tr></thead>
          <tbody>
            {loading ? <tr><td colSpan={3} className="px-4 py-8 text-center text-muted-foreground">Loading...</td></tr>
              : items.length === 0 ? <tr><td colSpan={3} className="px-4 py-8 text-center text-muted-foreground">No categories found</td></tr>
              : items.map(item => (
                <tr key={item.id} className="border-b last:border-0 hover:bg-muted/30">
                  <td className="px-4 py-3 font-medium">{item.name}</td>
                  <td className="px-4 py-3 text-muted-foreground">{new Date(item.created_at).toLocaleDateString()}</td>
                  <td className="px-4 py-3">
                    <div className="flex gap-1">
                      <Button size="sm" variant="ghost" onClick={() => { setEditing(item); setName(item.name); setFormError(''); setDialogOpen(true); }}><Edit className="h-3.5 w-3.5" /></Button>
                      <Button size="sm" variant="ghost" onClick={() => handleDelete(item.id)} className="text-destructive hover:text-destructive"><Trash2 className="h-3.5 w-3.5" /></Button>
                    </div>
                  </td>
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
      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="max-w-sm">
          <DialogHeader><DialogTitle>{editing ? 'Edit Category' : 'Add Category'}</DialogTitle></DialogHeader>
          {formError && <div className="rounded-md bg-destructive/10 border border-destructive/20 p-3 text-sm text-destructive">{formError}</div>}
          <div><Label>Name *</Label><Input value={name} onChange={(e) => setName(e.target.value)} maxLength={100} className="mt-1" /></div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDialogOpen(false)}>Cancel</Button>
            <Button onClick={handleSubmit} disabled={submitting}>{submitting ? 'Saving...' : 'Save'}</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
};

export default CategoriesPage;
