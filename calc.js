// calc.js
export const MIN_TOTAL = 950;
export const SRA3 = { w: 320, h: 450 };
export const SRA3_PLUS = { w: 330, h: 488 };
export const GAP = 3;
export const COLOR_MATCHING_PRICE = 725;
export const LAYOUT_PRICE = 145;

// Материалы, которые нельзя ламинировать
export const NON_LAMINATED_MATERIALS = [
    'Офсет бумага',
    'Лён',
    'Majestic светлый',
    'Touch cover светлый (plike)',
    'Крафт'
];

// Фиксированные значения для стандартных форматов на SRA3 (320×450)
const FORMAT_SHEETS = {
    'А3': 1, 'A3': 1,
    'А4': 2, 'A4': 2,
    'А5': 4, 'A5': 4,
    'А6': 8, 'A6': 8
};

// Материалы для формата 330×488мм
export const SRA3_PLUS_MATERIALS = [
    { 
        name: 'Мелованная бумага', 
        densities: [
            { value: 250, price: 23 },
            { value: 300, price: 28 }
        ]
    }
];

// Функция расчета стоимости биговки/фальцовки и перфорации в зависимости от тиража
export function getScoringFoldingPrice(quantity) {
    if (quantity <= 0) return 0;
    
    if (quantity === 1) return 59;
    if (quantity === 2) return 32;
    if (quantity === 3) return 23;
    if (quantity === 4) return 19;
    if (quantity === 5) return 16;
    if (quantity >= 10 && quantity < 20) return 11;
    if (quantity >= 20 && quantity < 30) return 8.2;
    if (quantity >= 30 && quantity < 50) return 7.3;
    if (quantity >= 50 && quantity < 100) return 6.5;
    if (quantity >= 100 && quantity < 500) return 6;
    if (quantity >= 500) return 5.5;
    
    return 0;
}

// Функция расчета стоимости склейки в зависимости от тиража
export function getGluingPrice(quantity) {
    if (quantity <= 0) return 0;
    
    if (quantity === 1) return 65;
    if (quantity === 2) return 38.5;
    if (quantity === 3) return 29;
    if (quantity === 4) return 24.75;
    if (quantity === 5) return 21.8;
    if (quantity >= 10 && quantity < 20) return 16.4;
    if (quantity >= 20 && quantity < 30) return 13.65;
    if (quantity >= 30 && quantity < 50) return 12.7;
    if (quantity >= 50 && quantity < 100) return 12;
    if (quantity >= 100 && quantity < 500) return 11.4;
    if (quantity >= 500) return 11;
    
    return 0;
}

// Функция расчета стоимости резки
export function getCuttingPrice(cuttingType, sheets, printPrice) {
    if (cuttingType === 'none' || cuttingType === 'plotter') return 0;
    
    if (cuttingType === 'guillotine_percent') {
        return printPrice * 0.1;
    }
    
    return 0;
}

// Функция проверки размещения на листе (БЕЗ отступов по краям)
export function checkFit(width, height, sheetWidth, sheetHeight) {
    // Размер печатного поля = полный размер листа
    // Отступы по краям НЕ делаем, только расстояние между изделиями
    const printableWidth = sheetWidth;
    const printableHeight = sheetHeight;
    
    console.log('Проверка размещения:', {
        листовка: `${width}×${height} мм`,
        лист: `${sheetWidth}×${sheetHeight} мм`,
        печатное_поле: `${printableWidth}×${printableHeight} мм`
    });
    
    // Добавляем расстояние между изделиями
    const itemW = width + GAP;
    const itemH = height + GAP;
    
    // Проверяем оба варианта размещения
    const cols1 = Math.floor(printableWidth / itemW);
    const rows1 = Math.floor(printableHeight / itemH);
    const fit1 = cols1 * rows1;
    
    const cols2 = Math.floor(printableWidth / itemH);
    const rows2 = Math.floor(printableHeight / itemW);
    const fit2 = cols2 * rows2;
    
    console.log('Расчет размещения:', {
        вариант1: `${cols1}×${rows1} = ${fit1}`,
        вариант2: `${cols2}×${rows2} = ${fit2}`
    });
    
    // Если ни один вариант не дает размещения, проверяем возможность размещения хотя бы одного изделия
    if (fit1 === 0 && fit2 === 0) {
        // Проверяем, помещается ли хотя бы одно изделие без учета GAP между ними
        if (width <= printableWidth && height <= printableHeight) {
            return {
                fits: true,
                count: 1,
                cols: 1,
                rows: 1,
                orientation: 'горизонтальная'
            };
        }
        if (height <= printableWidth && width <= printableHeight) {
            return {
                fits: true,
                count: 1,
                cols: 1,
                rows: 1,
                orientation: 'вертикальная'
            };
        }
    }
    
    const maxFit = Math.max(fit1, fit2);
    
    return {
        fits: maxFit > 0,
        count: maxFit,
        cols: fit1 >= fit2 ? cols1 : cols2,
        rows: fit1 >= fit2 ? rows1 : rows2,
        orientation: fit1 >= fit2 ? 'горизонтальная' : 'вертикальная'
    };
}

// Функция calculateFit для обратной совместимости
export function calculateFit(width, height, marginTop, marginBottom, marginLeft, marginRight, returnDetails = false) {
    // Эта функция больше не используется, оставлена для совместимости
    return checkFit(width, height, SRA3.w, SRA3.h);
}

export function calculate(input, data) {
    const {
        format,
        customWidth,
        customHeight,
        circulation,
        colorMode,
        material,
        density,
        lamination,
        cuttingType,
        scoringCount,
        foldingCount,
        gluingCount,
        deliveryCost,
        discountPercent,
        colorMatching,
        layoutsCount,
        prices,
        laminationPrices,
        scoringPrice,
        foldingPrice
    } = input;

    // Проверка на совместимость материала и ламинации
    const isNonLaminated = NON_LAMINATED_MATERIALS.includes(material.name);
    const hasLamination = lamination && !lamination.toLowerCase().includes('без ламинации');
    
    if (isNonLaminated && hasLamination) {
        throw new Error(`Материал "${material.name}" нельзя ламинировать`);
    }

    // Определяем используемый формат листа и материалы
    let currentSheetSize = SRA3;
    let usedMaterials = data.materials;
    let printPriceMultiplier = 1;
    let laminationPriceMultiplier = 1;
    let isSRA3Plus = false;
    let sheetSizeForDisplay = "320×450";
    
    // Расчет количества изделий на листе
    let perSheet;
    let fitDetails = { cols: 0, rows: 0, orientation: '' };
    
    if (format === 'custom') {
        // Сначала проверяем размещение на SRA3 (320×450)
        const fitResult = checkFit(customWidth, customHeight, SRA3.w, SRA3.h);
        
        if (fitResult.fits) {
            // Помещается на SRA3
            currentSheetSize = SRA3;
            usedMaterials = data.materials;
            printPriceMultiplier = 1;
            laminationPriceMultiplier = 1;
            perSheet = fitResult.count;
            fitDetails = fitResult;
            isSRA3Plus = false;
            sheetSizeForDisplay = "320×450";
        } else {
            // Проверяем на увеличенном формате 330×488мм
            const fitResultPlus = checkFit(customWidth, customHeight, SRA3_PLUS.w, SRA3_PLUS.h);
            
            if (!fitResultPlus.fits) {
                console.error('Не удалось разместить:', {
                    размер: `${customWidth}×${customHeight}`,
                    SRA3: `320×450`,
                    SRA3_PLUS: `330×488`
                });
                throw new Error(`Размер листовки ${customWidth}×${customHeight} мм не помещается ни на SRA3 (320×450 мм), ни на увеличенный формат (330×488 мм)`);
            }
            
            // Помещается на увеличенный формат
            currentSheetSize = SRA3_PLUS;
            usedMaterials = SRA3_PLUS_MATERIALS;
            printPriceMultiplier = 1.2;
            laminationPriceMultiplier = 1.2;
            perSheet = fitResultPlus.count;
            fitDetails = fitResultPlus;
            isSRA3Plus = true;
            sheetSizeForDisplay = "330×488";
        }
    } else {
        // Стандартные форматы всегда на SRA3 (320×450)
        perSheet = FORMAT_SHEETS[format] || 1;
        
        if (format === 'А3' || format === 'A3') {
            fitDetails = { cols: 1, rows: 1, orientation: 'горизонтальная', count: 1 };
        } else if (format === 'А4' || format === 'A4') {
            fitDetails = { cols: 2, rows: 1, orientation: 'горизонтальная', count: 2 };
        } else if (format === 'А5' || format === 'A5') {
            fitDetails = { cols: 2, rows: 2, orientation: 'горизонтальная', count: 4 };
        } else if (format === 'А6' || format === 'A6') {
            fitDetails = { cols: 4, rows: 2, orientation: 'горизонтальная', count: 8 };
        } else {
            fitDetails = { cols: 1, rows: 1, orientation: 'горизонтальная', count: 1 };
        }
        sheetSizeForDisplay = "320×450";
    }

    const sheets = Math.ceil(circulation / perSheet);

    // Цена материала (с учетом возможной смены материалов для увеличенного формата)
    let materialPricePerSheet;
    let actualMaterial = material;
    
    if (isSRA3Plus) {
        // Для увеличенного формата используем только мелованную бумагу
        const sra3PlusMaterial = SRA3_PLUS_MATERIALS[0];
        actualMaterial = sra3PlusMaterial;
        materialPricePerSheet = getMaterialPrice(sra3PlusMaterial, density);
    } else {
        materialPricePerSheet = getMaterialPrice(material, density);
        actualMaterial = material;
    }
    
    const materialPrice = sheets * materialPricePerSheet;

    // Цена печати с учетом множителя
    const basePrintPrice = getPrintPrice(sheets, colorMode, prices || data.prices);
    const printPrice = basePrintPrice * printPriceMultiplier;

    // Цена ламинации с учетом множителя
    const baseLaminationPrice = (!hasLamination || isNonLaminated) ? 0 : getLaminationPrice(sheets, lamination, laminationPrices || data.laminationPrices);
    const laminationPrice = baseLaminationPrice * laminationPriceMultiplier;

    // Цена биговки/фальцовки и перфорации
    const scoringPricePerUnit = scoringCount > 0 ? getScoringFoldingPrice(circulation) : 0;
    const foldingPricePerUnit = foldingCount > 0 ? getScoringFoldingPrice(circulation) : 0;
    
    const scoringPriceTotal = scoringCount * circulation * scoringPricePerUnit;
    const foldingPriceTotal = foldingCount * circulation * foldingPricePerUnit;
    
    // Цена склейки
    const gluingPricePerUnit = gluingCount > 0 ? getGluingPrice(circulation) : 0;
    const gluingPriceTotal = gluingCount * circulation * gluingPricePerUnit;

    // Цена резки
    const cuttingPriceTotal = getCuttingPrice(cuttingType, sheets, printPrice);

    // Подбор цвета и макеты
    const colorMatchingPriceTotal = colorMatching === 'yes' ? COLOR_MATCHING_PRICE : 0;
    const layoutsPriceTotal = (parseInt(layoutsCount) || 1) > 1 ? 
                              (parseInt(layoutsCount) || 1) * LAYOUT_PRICE : 0;

    // Базовая сумма (без доставки)
    let baseTotal = printPrice + materialPrice + laminationPrice + 
                    scoringPriceTotal + foldingPriceTotal + gluingPriceTotal +
                    cuttingPriceTotal + colorMatchingPriceTotal + layoutsPriceTotal;

    // Применяем минимальную стоимость к базовой сумме
    if (printPrice + materialPrice < MIN_TOTAL) {
        baseTotal = MIN_TOTAL + laminationPrice + scoringPriceTotal + 
                    foldingPriceTotal + gluingPriceTotal + cuttingPriceTotal +
                    colorMatchingPriceTotal + layoutsPriceTotal;
    }

    // Применяем скидку (в процентах) к базовой сумме
    const discountMultiplier = 1 - (discountPercent / 100);
    const discountedTotal = baseTotal * discountMultiplier;
    
    // Добавляем доставку ПОСЛЕ применения скидки
    const finalTotal = discountedTotal + (deliveryCost || 0);

    // Стоимость за штуку с учетом всего
    const perPiece = finalTotal / circulation;

    return {
        total: Math.round(finalTotal),
        perPiece: perPiece,
        baseTotal: Math.round(baseTotal),
        discountedTotal: Math.round(discountedTotal),
        perSheet: perSheet,
        fitDetails: fitDetails,
        materialPricePerSheet: materialPricePerSheet,
        actualMaterial: actualMaterial,
        colorMatchingPrice: colorMatchingPriceTotal,
        layoutsPrice: layoutsPriceTotal,
        scoringPrice: Math.round(scoringPriceTotal),
        scoringPricePerUnit: scoringPricePerUnit,
        foldingPrice: Math.round(foldingPriceTotal),
        foldingPricePerUnit: foldingPricePerUnit,
        gluingPrice: Math.round(gluingPriceTotal),
        gluingPricePerUnit: gluingPricePerUnit,
        cuttingPrice: Math.round(cuttingPriceTotal),
        discountAmount: Math.round(baseTotal * (discountPercent / 100)),
        deliveryCost: deliveryCost || 0,
        isSRA3Plus: isSRA3Plus,
        sheetSizeForDisplay: sheetSizeForDisplay,
        breakdown: {
            sheets,
            printPrice: Math.round(printPrice),
            materialPrice: Math.round(materialPrice),
            laminationPrice: Math.round(laminationPrice),
            scoringPrice: Math.round(scoringPriceTotal),
            foldingPrice: Math.round(foldingPriceTotal),
            gluingPrice: Math.round(gluingPriceTotal),
            cuttingPrice: Math.round(cuttingPriceTotal),
            colorMatchingPrice: Math.round(colorMatchingPriceTotal),
            layoutsPrice: Math.round(layoutsPriceTotal)
        }
    };
}

// Функция получения цены материала
export function getMaterialPrice(material, density) {
    if (!material) return 10;
    if (!material.densities || !Array.isArray(material.densities)) return 10;
    
    const densityOption = material.densities.find(d => Math.abs(d.value - density) < 0.01);
    
    if (densityOption) {
        return densityOption.price;
    }
    
    return material.densities[0]?.price || 10;
}

// Функция форматирования размещения
export function formatFit(format) {
    const map = {
        'А3': 1, 'A3': 1,
        'А4': 2, 'A4': 2,
        'А5': 4, 'A5': 4,
        'А6': 8, 'A6': 8
    };
    return map[format] || 1;
}

// Функция получения цены печати
export function getPrintPrice(qty, mode, table) {
    if (!table || !table.length) return 0;
    const key = `price_${mode}`;
    const row = table.find(r => qty >= r.min);
    return row && row[key] ? qty * row[key] : 0;
}

// Функция получения цены ламинации
export function getLaminationPrice(qty, type, table) {
    if (!table || !table.length) return 0;
    
    const map = {
        'глянцевая 32': 'gloss_32',
        'матовая 32': 'matte_32',
        'глянцевая 75': 'gloss_75',
        'матовая 75': 'matte_75',
        'глянцевая 125': 'gloss_125',
        'матовая 125': 'matte_125',
        'soft touch': 'soft_touch'
    };

    const normalizedType = type.toLowerCase().trim();
    const key = map[normalizedType] || map[type];
    
    if (!key) return 0;
    
    const row = table.find(r => qty >= r.min);
    return row && row[key] ? qty * row[key] : 0;
}

// Функция получения цены печати за лист
export function getPrintPricePerSheet(qty, mode, table) {
    if (!table || !table.length) return 0;
    const key = `price_${mode}`;
    const row = table.find(r => qty >= r.min);
    return row && row[key] ? row[key] : 0;
}

// Функция получения цены ламинации за лист
export function getLaminationPricePerSheet(qty, type, table) {
    if (!table || !table.length || !type || type.toLowerCase().includes('без ламинации')) return 0;
    
    const map = {
        'глянцевая 32': 'gloss_32',
        'матовая 32': 'matte_32',
        'глянцевая 75': 'gloss_75',
        'матовая 75': 'matte_75',
        'глянцевая 125': 'gloss_125',
        'матовая 125': 'matte_125',
        'soft touch': 'soft_touch'
    };

    const normalizedType = type.toLowerCase().trim();
    const key = map[normalizedType] || map[type];
    
    if (!key) return 0;
    
    const row = table.find(r => qty >= r.min);
    return row && row[key] ? row[key] : 0;
}