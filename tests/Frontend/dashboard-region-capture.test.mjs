import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import test from "node:test";
import vm from "node:vm";

const source = readFileSync(
    new URL("../../resources/js/app.js", import.meta.url),
    "utf8",
);
const component = source.slice(
    source.indexOf("window.dashboardRegionCapture ="),
    source.indexOf("window.dashboardMatrixModal ="),
);

function element(attributes = {}, children = [], overrides = {}) {
    const attrs = new Map(Object.entries(attributes));

    return {
        style: {},
        parentElement: null,
        get attributes() {
            return [...attrs].map(([name, value]) => ({ name, value }));
        },
        querySelectorAll: () => children,
        removeAttribute: (name) => attrs.delete(name),
        setAttribute: (name, value) => attrs.set(name, value),
        hasAttribute: (name) => attrs.has(name),
        getBoundingClientRect: () => ({ width: 960 }),
        cloneNode: () =>
            element(
                Object.fromEntries(attrs),
                children.map((child) => child.cloneNode()),
            ),
        _attrs: attrs,
        ...overrides,
    };
}

function capture(region, format = "png", pngOverride = null, options = {}) {
    const calls = { png: [], jpeg: [] };
    const link = { download: "", href: "", click() {} };
    const node = options.node ?? element();
    let holder = null;

    const context = vm.createContext({
        window: {},
        document: {
            createElement: (tag) => {
                if (tag === "a") return link;

                holder = {
                    style: {},
                    setAttribute() {},
                    appendChild() {},
                    remove() {},
                };

                return holder;
            },
            querySelector: (selector) =>
                selector === "#dashboard-program-matrix" ? node : null,
            body: { appendChild() {} },
        },
        getComputedStyle: () => ({ backgroundColor: "rgb(22, 27, 34)" }),
        requestAnimationFrame: (callback) => {
            callback();
            return 1;
        },
        toPng:
            pngOverride ??
            (async (target, renderOptions) => {
                calls.png.push({ node: target, options: renderOptions });
                return "data:image/png;base64,abc";
            }),
        toJpeg: async (target, renderOptions) => {
            calls.jpeg.push({ node: target, options: renderOptions });
            return "data:image/jpeg;base64,abc";
        },
        console: { error() {}, log: console.log, warn: console.warn },
    });
    vm.runInContext(component, context);

    const model = context.window.dashboardRegionCapture(
        region,
        options.target ?? "",
    );
    model.format = format;
    model.$refs = options.useRef === false ? {} : { captureArea: node };

    return { model, node, link, calls, holder: () => holder };
}

test("capture region menghasilkan PNG dengan nama file dan latar sesuai kartu", async () => {
    const { model, link, calls } = capture("REGION BALNUS");

    await model.download();

    assert.equal(model.capturing, false);
    assert.equal(model.captureError, "");
    assert.equal(calls.png.length, 1);
    assert.notEqual(calls.png[0].node, null);
    assert.equal(calls.png[0].options.pixelRatio, 2);
    assert.equal(calls.png[0].options.skipFonts, true);
    assert.equal(calls.png[0].options.backgroundColor, "rgb(22, 27, 34)");
    assert.match(link.download, /^region-balnus-\d{4}-\d{2}-\d{2}\.png$/);
    assert.equal(link.href, "data:image/png;base64,abc");
});

test("format JPEG memakai kualitas tinggi dan ekstensi jpg", async () => {
    const { model, link, calls } = capture("REGION BALNUS", "jpeg");

    await model.download();

    assert.equal(calls.jpeg.length, 1);
    assert.equal(calls.jpeg[0].options.quality, 0.95);
    assert.match(link.download, /^region-balnus-\d{4}-\d{2}-\d{2}\.jpg$/);
});

test("direktif Alpine dibuang dari clone agar SVG valid", async () => {
    const button = element({
        "@click": "open()",
        ":class": "active",
        "x-show": "open",
        "x-text": "label",
        "data-capture-ignore": "",
        class: "font-bold",
    });
    const node = element({ "x-data": "{ open: false }" }, [button]);
    const { model, calls } = capture("REGION JATIM", "png", null, { node });

    await model.download();

    const captured = calls.png[0].node;
    assert.deepEqual([...captured._attrs.keys()], []);
    assert.deepEqual([...captured.querySelectorAll()[0]._attrs.keys()], [
        "data-capture-ignore",
        "class",
    ]);
});

test("kontrol tombol dan format tidak ikut masuk ke gambar", async () => {
    const { model, calls } = capture("REGION JATIM");
    await model.download();

    const { filter } = calls.png[0].options;
    assert.equal(
        filter({ hasAttribute: (name) => name === "data-capture-ignore" }),
        false,
    );
    assert.equal(filter({ hasAttribute: () => false }), true);
});

test("capture admin memakai selector matrix di luar komponen filter", async () => {
    const { model, calls } = capture(
        "Branch · SURABAYA",
        "png",
        null,
        { target: "#dashboard-program-matrix", useRef: false },
    );

    await model.download();

    assert.equal(calls.png.length, 1);
    assert.notEqual(calls.png[0].node, null);
});

test("tabel dilebarkan agar semua kolom dan nama service area utuh", async () => {
    const label = element();
    const scrollArea = element();
    const table = element({}, [], {
        getBoundingClientRect: () => ({ width: 1200 }),
        closest: () => scrollArea,
    });
    const clone = element({}, [], {
        querySelector: (selector) => (selector === "table" ? table : null),
        querySelectorAll: (selector) =>
            selector === "button .wrap-break-word" ? [label] : [],
    });
    const node = element({}, [], {
        getBoundingClientRect: () => ({ width: 960 }),
        cloneNode: () => clone,
    });
    const { model, calls } = capture("Branch · SURABAYA", "png", null, { node });

    await model.download();

    assert.equal(clone.style.width, "1202px");
    assert.equal(scrollArea.style.overflow, "visible");
    assert.equal(label.style.whiteSpace, "nowrap");
    assert.equal(label.style.overflowWrap, "normal");
    assert.equal(table.style.width, "100%");
    assert.equal(table.style.minWidth, "1200px");
    assert.equal(calls.png[0].node, clone);
});

test("gagal pada rasio 2 dicoba ulang dengan rasio 1", async () => {
    let attempts = 0;
    const { model, calls } = capture("REGION JATIM", "png", async (target, options) => {
        attempts += 1;
        calls.png.push({ node: target, options });
        if (attempts === 1) throw new Error("canvas terlalu besar");

        return "data:image/png;base64,abc";
    });

    await model.download();

    assert.equal(attempts, 2);
    assert.equal(calls.png[1].options.pixelRatio, 1);
    assert.equal(model.captureError, "");
    assert.equal(model.capturing, false);
});

test("kegagalan render menampilkan pesan dan status tombol kembali normal", async () => {
    const { model } = capture("REGION JATIM", "png", async () => {
        throw new Error("canvas gagal");
    });

    await model.download();

    assert.equal(model.capturing, false);
    assert.equal(model.captureError, "Gagal membuat gambar. Coba lagi.");
});