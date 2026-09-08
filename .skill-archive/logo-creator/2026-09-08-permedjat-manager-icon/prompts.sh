#!/bin/bash
# Permedjat "Manager" app icon — 5 concept tracks, 4 variations each = 20
set -u
BASE="square 1:1 app icon. Exactly two flat colours: a solid warm gold #C9A227 mark on a solid warm dark brown #2A2522 background. No gradients, no shadows, no texture, no outline, no highlight, no bevel, no 3D. Modern flat design in the spirit of Notion and Linear app icons: bold clean geometry, thick shapes, strong symmetry, generous even padding, perfectly centred, must stay readable at 16 pixels. No text, no letters, no numbers, no hieroglyphs, no religious or mythological symbols, no ankh, no eye, no sun disk, no winged disc, no deities, no people, no photorealism."

run () { # $1 = track name, $2 = subject
  echo "=== $1 ==="
  gemini --yolo "/generate '$2 $BASE' --count=4"
}

run solid-pylon      "A simplified ancient Egyptian temple gateway (pylon): two tapered trapezoidal towers flanking a tall central doorway, standing on a solid horizontal base platform, drawn as one solid gold silhouette."
run negative-door    "A simplified ancient Egyptian temple gateway (pylon) drawn as a solid gold block with the tall central doorway cut clean out of it as negative space, sitting on a separate horizontal base bar."
run cornice          "A simplified ancient Egyptian pylon gateway whose two tapered towers are capped by a flared cavetto cornice, a slim lintel bridging them above a tall doorway, on a base platform."
run bars             "An extremely reduced ancient Egyptian gateway built from only four thick gold bars: two tapered uprights, one horizontal lintel across the top, one wide base bar beneath."
run stepped          "A simplified ancient Egyptian gateway with subtly stepped/battered tower walls narrowing toward the top, a tall arch-free rectangular doorway between them, resting on a wide plinth."
