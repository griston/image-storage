# Image storage examples

## Basic usage

```latte
{img $imageIdentifier}
<img n:img="$imageIdentifier">
{imgLink $imageIdentifier}
```

## Resize

Both dimensions can be provided:

```latte
<img n:img="$imageIdentifier, '300x200'">
```

One dimension can be omitted. The missing dimension is calculated from the original image ratio:

```latte
<img n:img="$imageIdentifier, '300x'">
<img n:img="$imageIdentifier, 'x200'">
```

## Transform modes

The third argument selects the resize mode:

```latte
<img n:img="$imageIdentifier, '300x200', 'fit'">
<img n:img="$imageIdentifier, '300x200', 'fill'">
<img n:img="$imageIdentifier, '300x200', 'exact'">
<img n:img="$imageIdentifier, '300x200', 'stretch'">
<img n:img="$imageIdentifier, '300x200', 'shrink_only'">
```

Modes can be combined with `+`:

```latte
<img n:img="$imageIdentifier, '300x200', 'fit+shrink_only'">
```

Available modes:

- `fit`: preserve ratio and fit into the requested box.
- `fill`: preserve ratio and fill the requested box.
- `exact`: resize to exact dimensions.
- `stretch`: allow stretching.
- `shrink_only`: do not enlarge smaller images.

## Quality

The fourth argument sets output quality:

```latte
<img n:img="$imageIdentifier, '300x200', 'fit', 90">
```

## Disable WebP conversion

WebP conversion is enabled by default. Pass `false` as the fifth argument to keep the resized image in the original format:

```latte
<img n:img="$imageIdentifier, '1200x630', 'fit', 90, false">
```

PHP usage:

```php
$image = $imageStorage->fromIdentifier([$identifier, '1200x630', 'fit', 90, false]);
```

## Responsive images

When the size argument contains multiple comma-separated values, `img` and `n:img` automatically generate `srcset`.

```latte
<img n:img="$imageIdentifier, '200x,400x,800x,1600x', 'fit'">
{img $imageIdentifier, '200x,400x,800x,1600x', 'fit'}
```

This renders `src` from the first size and `srcset` from all provided sizes.

When all variants share the same height, you can pass only widths and the fixed height. The helper generates `src`, `srcset`, and a default Bootstrap card-grid `sizes` attribute:

```latte
<img
    {$imageStorage->createResponsiveImgAttributes($imageIdentifier, '540,720,960,1140,1320', 216, $basePath, 'shrink_only+fit')|noescape}
    alt=""
>
```

You can override the generated `sizes` value with the last argument:

```latte
<img
    {$imageStorage->createResponsiveImgAttributes($imageIdentifier, '540,720,960', 216, $basePath, 'exact', null, true, '(min-width: 768px) 50vw, 100vw')|noescape}
    alt=""
>
```

## Image source attributes

Use `imgSrc` or `imgsrc` when you only need image attributes for an existing tag:

```latte
<img {imgSrc $imageIdentifier, '200x,400x,800x', 'fit'} alt="">
<img {imgsrc $imageIdentifier, '200x,400x,800x', 'fit'} alt="">
```

Absolute variants use `$baseUrl`:

```latte
{imgAbs $imageIdentifier, '1200x630', 'fit'}
{imgSrcAbs $imageIdentifier, '1200x630', 'fit'}
```

## Direct links

`imgLink` returns only the image URL and uses the first size when multiple sizes are provided:

```latte
{imgLink $imageIdentifier, '800x', 'fit'}
```
