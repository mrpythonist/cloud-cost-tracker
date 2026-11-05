export default function MonthPicker({
  value,
  onChange,
  label,
}: {
  value: string;
  onChange: (month: string) => void;
  label: string;
}) {
  return (
    <label className="flex flex-col gap-1 text-sm font-medium text-slate-700">
      {label}
      <input
        type="month"
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className="rounded border border-slate-300 px-3 py-2 text-slate-900"
      />
    </label>
  );
}
