import { Head, Link, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Button from '@/Components/Button';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import Checkbox from '@/Components/Checkbox';
import { ArrowLeftIcon } from '@heroicons/react/24/outline';

/**
 * Form de linha da DRE. Reaproveitado entre create (line=null) e edit.
 *
 * Campo `accumulate_until_sort_order` só é relevante quando is_subtotal=true —
 * escondemos nesse caso, mas mantemos o valor no form pra não perder na edição.
 */
export default function ManagementLineEdit({ line, natureOptions }) {
    const isEditing = line !== null && line !== undefined;

    const { data, setData, post, put, processing, errors } = useForm({
        code: line?.code ?? '',
        sort_order: line?.sort_order ?? 1,
        is_subtotal: line?.is_subtotal ?? false,
        accumulate_until_sort_order: line?.accumulate_until_sort_order ?? null,
        level_1: line?.level_1 ?? '',
        level_2: line?.level_2 ?? '',
        level_3: line?.level_3 ?? '',
        level_4: line?.level_4 ?? '',
        nature: line?.nature ?? 'expense',
        is_active: line?.is_active ?? true,
        notes: line?.notes ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        if (isEditing) {
            put(route('dre.management-lines.update', line.id));
        } else {
            post(route('dre.management-lines.store'));
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title={isEditing ? 'Editar linha DRE' : 'Nova linha DRE'} />

            <div className="py-8">
                <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="mb-6">
                        <Link
                            href={route('dre.management-lines.index')}
                            className="inline-flex items-center gap-1 text-sm text-gray-600 hover:text-gray-900 mb-2"
                        >
                            <ArrowLeftIcon className="h-4 w-4" />
                            Voltar para o plano gerencial
                        </Link>
                        <h1 className="text-2xl font-semibold text-gray-900">
                            {isEditing ? `Editar linha "${line.level_1}"` : 'Nova linha da DRE'}
                        </h1>
                    </div>

                    <form
                        onSubmit={submit}
                        className="bg-white shadow-sm rounded-lg p-6 space-y-5"
                    >
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <InputLabel htmlFor="code" value="Código" />
                                <TextInput
                                    id="code"
                                    className="w-full"
                                    value={data.code}
                                    onChange={(e) => setData('code', e.target.value)}
                                    placeholder="Ex: L21"
                                    required
                                />
                                <InputError className="mt-1" message={errors.code} />
                            </div>

                            <div>
                                <InputLabel htmlFor="sort_order" value="Ordem" />
                                <TextInput
                                    id="sort_order"
                                    type="number"
                                    min="1"
                                    className="w-full"
                                    value={data.sort_order}
                                    onChange={(e) =>
                                        setData('sort_order', parseInt(e.target.value, 10) || 1)
                                    }
                                    required
                                />
                                <InputError className="mt-1" message={errors.sort_order} />
                            </div>
                        </div>

                        <div>
                            <InputLabel htmlFor="level_1" value="Rótulo (level_1)" />
                            <TextInput
                                id="level_1"
                                className="w-full"
                                value={data.level_1}
                                onChange={(e) => setData('level_1', e.target.value)}
                                placeholder="Ex: (+) Faturamento Bruto"
                                required
                            />
                            <InputError className="mt-1" message={errors.level_1} />
                        </div>

                        <div>
                            <InputLabel htmlFor="nature" value="Natureza" />
                            <select
                                id="nature"
                                value={data.nature}
                                onChange={(e) => setData('nature', e.target.value)}
                                className="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm w-full"
                            >
                                {(natureOptions || []).map((opt) => (
                                    <option key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </option>
                                ))}
                            </select>
                            <InputError className="mt-1" message={errors.nature} />
                        </div>

                        <div className="flex items-start gap-3">
                            <Checkbox
                                checked={data.is_subtotal}
                                onChange={(e) => setData('is_subtotal', e.target.checked)}
                                id="is_subtotal"
                            />
                            <div>
                                <InputLabel htmlFor="is_subtotal" value="É subtotal?" />
                                <p className="text-xs text-gray-500">
                                    Subtotais acumulam linhas anteriores até a ordem configurada
                                    abaixo.
                                </p>
                            </div>
                        </div>

                        {data.is_subtotal && (
                            <div>
                                <InputLabel
                                    htmlFor="accumulate_until_sort_order"
                                    value="Acumular até a ordem"
                                />
                                <TextInput
                                    id="accumulate_until_sort_order"
                                    type="number"
                                    min="1"
                                    className="w-full"
                                    value={data.accumulate_until_sort_order ?? ''}
                                    onChange={(e) =>
                                        setData(
                                            'accumulate_until_sort_order',
                                            e.target.value === ''
                                                ? null
                                                : parseInt(e.target.value, 10)
                                        )
                                    }
                                />
                                <InputError
                                    className="mt-1"
                                    message={errors.accumulate_until_sort_order}
                                />
                            </div>
                        )}

                        <div className="flex items-center gap-2">
                            <Checkbox
                                checked={data.is_active}
                                onChange={(e) => setData('is_active', e.target.checked)}
                                id="is_active"
                            />
                            <InputLabel htmlFor="is_active" value="Ativa" />
                        </div>

                        <div>
                            <InputLabel htmlFor="notes" value="Observações" />
                            <textarea
                                id="notes"
                                rows="3"
                                className="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm w-full"
                                value={data.notes ?? ''}
                                onChange={(e) => setData('notes', e.target.value)}
                            />
                            <InputError className="mt-1" message={errors.notes} />
                        </div>

                        <div className="flex justify-end gap-2 pt-4 border-t">
                            <Link href={route('dre.management-lines.index')}>
                                <Button variant="secondary" type="button">
                                    Cancelar
                                </Button>
                            </Link>
                            <Button type="submit" variant="primary" loading={processing}>
                                {isEditing ? 'Salvar alterações' : 'Criar linha'}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
