# summarize_excels.py
# Reads Excel/CSV files in the same folder and prints short descriptions.
# Requires: pandas, openpyxl, xlrd (for .xls). Install with:
#    pip install pandas openpyxl xlrd

import os
import sys
from datetime import datetime

try:
    import pandas as pd
except Exception as e:
    print("Missing dependency: pandas. Install with: pip install pandas openpyxl xlrd")
    sys.exit(2)

FOLDER = os.path.dirname(os.path.abspath(__file__))
EXTS = ('.xlsx', '.xls', '.xlsm', '.xlsb', '.csv', '.txt')


def describe_dataframe(df, max_cols=6):
    cols = list(df.columns[:max_cols])
    types = [str(df[c].dtype) for c in cols]
    non_null = [int(df[c].notna().sum()) for c in cols]
    samples = []
    for c in cols:
        vals = df[c].dropna().unique()[:3].tolist()
        samples.append(vals)
    return cols, types, non_null, samples


def summarize_file(path):
    st = os.stat(path)
    size_kb = st.st_size / 1024.0
    mtime = datetime.fromtimestamp(st.st_mtime).isoformat()
    summary = {"path": path, "size_kb": round(size_kb,2), "modified": mtime, "sheets": []}
    ext = os.path.splitext(path)[1].lower()
    try:
        if ext in ('.xlsx', '.xls', '.xlsm', '.xlsb'):
            xls = pd.ExcelFile(path)
            for sheet in xls.sheet_names:
                try:
                    df = pd.read_excel(xls, sheet_name=sheet, nrows=6)
                    cols, types, non_null, samples = describe_dataframe(df)
                    summary["sheets"].append({"sheet_name": sheet, "columns": cols, "dtypes": types, "non_null": non_null, "samples": samples, "rows_sampled": len(df)})
                except Exception as e:
                    summary["sheets"].append({"sheet_name": sheet, "error": str(e)})
        elif ext in ('.csv', '.txt'):
            df = pd.read_csv(path, nrows=6)
            cols, types, non_null, samples = describe_dataframe(df)
            summary["sheets"].append({"sheet_name": 'csv', "columns": cols, "dtypes": types, "non_null": non_null, "samples": samples, "rows_sampled": len(df)})
        else:
            summary["error"] = "Unsupported extension"
    except Exception as e:
        summary["error"] = str(e)
    return summary


def make_description(s):
    if "error" in s:
        return f"Could not parse {os.path.basename(s['path'])}: {s['error']}"
    parts = []
    fname = os.path.basename(s['path'])
    parts.append(f"{fname} ({s['size_kb']} KB, modified {s['modified']})")
    if not s['sheets']:
        parts.append("contains no readable sheets.")
        return ". ".join(parts)
    sheet_descs = []
    for sh in s['sheets'][:2]:
        if 'error' in sh:
            sheet_descs.append(f"sheet '{sh.get('sheet_name','?')}' could not be read")
            continue
        cols = sh.get('columns', [])
        ncols = len(cols)
        nrows = sh.get('rows_sampled', 0)
        sample_col = cols[0] if cols else 'col1'
        example_vals = sh.get('samples', [[]])
        examples = ', '.join([str(v) for v in (example_vals[0][:3] if example_vals else [])])
        sheet_descs.append(f"sheet '{sh['sheet_name']}' (~{ncols} cols; sample rows: {nrows}; first col '{sample_col}' examples: {examples})")
    parts.append('; '.join(sheet_descs))
    return '. '.join(parts)


def main(folder):
    folder = os.path.abspath(folder)
    out = []
    for root,_,files in os.walk(folder):
        for f in files:
            if f.lower().endswith(EXTS):
                path = os.path.join(root,f)
                try:
                    s = summarize_file(path)
                    s['suggested_description'] = make_description(s)
                    out.append(s)
                except Exception as e:
                    out.append({"path": path, "error": str(e)})
    if not out:
        print("No excel or csv files found in", folder)
        return
    for s in out:
        print('----')
        print('Path:', s.get('path'))
        if 'error' in s:
            print('Error:', s['error'])
            continue
        print('Suggested description:')
        print(s.get('suggested_description'))
    print('----\nDone.')

if __name__ == '__main__':
    main(FOLDER)
