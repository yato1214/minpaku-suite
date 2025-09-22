#!/usr/bin/env python
import struct

def compile_po_to_mo(po_file, mo_file):
    with open(po_file, 'r', encoding='utf-8') as f:
        content = f.read()

    entries = []
    lines = content.split('\n')
    i = 0

    while i < len(lines):
        line = lines[i].strip()
        if line.startswith('msgid ') and line != 'msgid ""':
            msgid = line[7:-1] if line.endswith('"') else line[6:]
            i += 1
            while i < len(lines) and lines[i].strip().startswith('"'):
                msgid += lines[i].strip()[1:-1]
                i += 1

            if i < len(lines) and lines[i].strip().startswith('msgstr '):
                msgstr_line = lines[i].strip()
                msgstr = msgstr_line[8:-1] if msgstr_line.endswith('"') else msgstr_line[7:]
                i += 1
                while i < len(lines) and lines[i].strip().startswith('"'):
                    msgstr += lines[i].strip()[1:-1]
                    i += 1

                if msgstr and msgid:
                    entries.append((msgid, msgstr))
        else:
            i += 1

    print(f'Found {len(entries)} translation entries')

    with open(mo_file, 'wb') as f:
        f.write(struct.pack('<I', 0x950412de))
        f.write(struct.pack('<I', 0))
        f.write(struct.pack('<I', len(entries)))
        f.write(struct.pack('<I', 28))
        f.write(struct.pack('<I', 28 + 8 * len(entries)))
        f.write(struct.pack('<I', 0))
        f.write(struct.pack('<I', 0))

        offset = 28 + 16 * len(entries)
        orig_offsets = []
        trans_offsets = []
        strings = []

        for msgid, msgstr in entries:
            msgid_bytes = msgid.encode('utf-8')
            msgstr_bytes = msgstr.encode('utf-8')
            orig_offsets.append((len(msgid_bytes), offset))
            offset += len(msgid_bytes) + 1
            trans_offsets.append((len(msgstr_bytes), offset))
            offset += len(msgstr_bytes) + 1
            strings.append((msgid_bytes, msgstr_bytes))

        for length, offset in orig_offsets:
            f.write(struct.pack('<II', length, offset))
        for length, offset in trans_offsets:
            f.write(struct.pack('<II', length, offset))

        for msgid_bytes, msgstr_bytes in strings:
            f.write(msgid_bytes + b'\x00')
            f.write(msgstr_bytes + b'\x00')

    print('Successfully generated MO file')

compile_po_to_mo('connectors/wp-minpaku-connector/languages/wp-minpaku-connector-ja.po', 'connectors/wp-minpaku-connector/languages/wp-minpaku-connector-ja.mo')